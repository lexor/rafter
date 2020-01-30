<?php

namespace App\GoogleCloud;

use App\Deployment;

class CloudBuildConfig
{
    const DOCKERFILES = [
        'laravel' => 'https://storage.googleapis.com/rafter-dockerfiles/Dockerfile-laravel',
    ];

    protected $attributes = [];
    protected $manual = false;
    protected $deployment;
    protected $environment;

    public function __construct(Deployment $deployment) {
        $this->deployment = $deployment;
        $this->environment = $deployment->environment;
    }

    /**
     * Mark a manual deployment (i.e. not Git-based)
     *
     * @param string $bucket
     * @param string $object
     * @return self
     */
    public function forManualPush($bucket, $object)
    {
        $this->manual = true;
        $this->attributes['bucket'] = $bucket;
        $this->attributes['object'] = $object;

        return $this;
    }

    /**
     * Whether it's a manual deployment
     *
     * @return boolean
     */
    public function isManual()
    {
        return $this->manual;
    }

    /**
     * Whether it's a git-based project
     *
     * @return boolean
     */
    public function isGitBased()
    {
        return ! $this->isManual();
    }

    /**
     * Get the name of the blank (public) bucket hosting the blank ZIP for Git builds.
     *
     * @return string
     */
    protected function blankBucket()
    {
        return 'rafter-dockerfiles';
    }

    /**
     * Get the name of the blank ZIP file to be used to start Git builds.
     *
     * @return string
     */
    protected function blankZip()
    {
        return 'blank.tar.gz';
    }

    /**
     * Get the source spec for the Build.
     *
     * @return array
     */
    public function source()
    {
        if ($this->isManual()) {
            return [
                'storageSource' => [
                    'bucket' => $this->attributes['bucket'],
                    'object' => $this->attributes['object'],
                ],
            ];
        } else {
            return [
                'storageSource' => [
                    'bucket' => $this->blankBucket(),
                    'object' => $this->blankZip(),
                ]
            ];
        }
    }

    /**
     * The step to download the Git repository
     *
     * @return array
     */
    protected function downloadGitRepoStep()
    {
        return [
            'name' => 'gcr.io/cloud-builders/git',
            'args' => ['clone', '--depth=1', "https://x-access-token:{$this->deployment->sourceProvider()->client()->token()}@github.com/{$this->deployment->repository()}.git"],
        ];

        // TODO: Get tarball working, as it will be much smaller
        // return [
        //     'name' => 'gcr.io/cloud-builders/curl',
        //     'args' => [$this->deployment->tarballUrl(), '-L', '--output', 'repo.tar.gz'],
        // ];
    }

    /**
     * Get the name of the repo's project
     *
     * @return string
     */
    protected function repoName()
    {
        return explode('/', $this->deployment->repository())[1];
    }

    /**
     * The steps required to build this image
     *
     * @return array
     */
    public function steps()
    {
        $steps = [
            // Pull the image down so we can build from cache
            [
                'name' => 'gcr.io/cloud-builders/docker',
                'entrypoint' => 'bash',
                'args' => ['-c', "docker pull {$this->imageLocation()}:latest || exit 0"],
            ],

            $this->isGitBased() ? $this->downloadGitRepoStep() : [],

            // Copy the Dockerfile we need
            [
                'name' => 'gcr.io/cloud-builders/curl',
                'args' => [static::DOCKERFILES['laravel'], '--output', 'Dockerfile'],
                'dir' => $this->isGitBased() ? $this->repoName() : '',
            ],

            // TEST: Show the dir
            [
                'name' => 'ubuntu',
                'args' => ['ls', '-la', './'],
                'dir' => $this->isGitBased() ? $this->repoName() : '',
            ],

            // Build the image
            [
                'name' => 'gcr.io/cloud-builders/docker',
                'args' => [
                    'build',
                    '-t', $this->imageLocation(),
                    '--cache-from', "{$this->imageLocation()}:latest",
                    '.'
                ],
                'dir' => $this->isGitBased() ? $this->repoName() : '',
            ],

            // Upload it to GCR
            [
                'name' => 'gcr.io/cloud-builders/docker',
                'args' => ['push', $this->imageLocation()],
                'dir' => $this->isGitBased() ? $this->repoName() : '',
            ],
        ];

        return collect($steps)->filter();
    }

    /**
     * The location of the image on GCR
     *
     * @return string
     */
    public function imageLocation()
    {
        return "gcr.io/\$PROJECT_ID/{$this->environment->slug()}";
    }

    /**
     * The images that will be built.
     *
     * @return array
     */
    public function images()
    {
        return [
            $this->imageLocation(),
        ];
    }

    /**
     * The instructions to send to the Cloud Build API
     *
     * @return array
     */
    public function instructions()
    {
        return [
            'source' => $this->source(),
            'steps' => $this->steps(),
            'images' => $this->images(),
        ];
    }
}