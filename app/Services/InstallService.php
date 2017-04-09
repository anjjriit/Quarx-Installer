<?php

namespace App\Services;

use Illuminate\Filesystem\Filesystem;

class InstallService
{
    /**
     * Directory for app.
     *
     * @var string
     */
    protected $directory;

    /**
     * Name for app.
     *
     * @var string
     */
    protected $app_name;

    /**
     * Constructor.
     *
     * @param Filesystem $fileSystem
     */
    public function __construct(Filesystem $fileSystem)
    {
        $this->fileSystem = $fileSystem;
    }

    /**
     * Get the app version.
     *
     * @return string
     */
    public function handle($name)
    {
        $this->directory = getcwd().'/'.$name;
        $this->app_name = $name;

        if (is_dir(getcwd().'/'.$this->app_name)) {
            throw new \Exception("This $this->app_name directory already exists", 1);
        }

        exec('git clone --depth 1 -b master https://github.com/yabhq/quantum.git '.$this->directory);

        if ($this->setUpApp()) {
            return true;
        }

        return false;
    }

    /**
     * Set up the app.
     */
    public function setUpApp()
    {
        if ($this->setComposerFile()) {
            $this->fileSystem->deleteDirectory($this->directory.'/.git');
            $this->composerInstall();
            $this->setEnvFile();
            $this->runKeyGenerator();
            $this->makeDatabase();
        }
    }

    /**
     * Set the details of the app.
     *
     * @param string $name
     */
    public function setComposerFile()
    {
        $appNamespace = 'company/'.strtolower($this->app_name);

        $composer = file_get_contents($this->directory.'/composer.json');
        $composer = str_replace('{appNamespace}', $appNamespace, $composer);

        if (file_put_contents($this->directory.'/composer.json', $composer)) {
            return true;
        }

        return false;
    }

    /**
     * Run composer install.
     */
    public function composerInstall()
    {
        passthru('composer install -d='.$this->directory);
    }

    /**
     * Run composer install.
     */
    public function setEnvFile()
    {
        $env = file_get_contents($this->directory.'/.env.example');
        $env = str_replace('APP_NAME=', 'APP_NAME='.$this->app_name, $env);
        file_put_contents($this->directory.'/.env', $env);
    }

    /**
     * Update the Readme.
     */
    public function updateTheReadMe()
    {
        $content = "#".$this->app_name."\n\nYour app's readme!";
        file_put_contents($this->directory.'/.readme.md', $content);
    }

    /**
     * Run composer install.
     */
    public function runKeyGenerator()
    {
        passthru('php '.$this->directory.'/artisan key:generate');
    }
}
