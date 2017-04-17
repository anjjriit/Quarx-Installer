<?php

namespace QuarxInstaller\Services;

use Illuminate\Filesystem\Filesystem;

class NewAppService
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
    public function handle($name, $ip = null)
    {
        $this->ip = $ip;
        $this->directory = getcwd().'/'.$name;
        $this->app_name = $name;

        if (is_dir(getcwd().'/'.$this->app_name)) {
            throw new \Exception("This $this->app_name directory already exists", 1);
        }

        exec('composer create-project laravel/laravel '.$this->directory);

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
            $this->setConfig();
            $this->vendorPublish();
            $this->makeDatabase();
            $this->startQuarx();
            $this->updateTheReadMe();
            $this->envCleanup();
        }
    }

    /**
     * Set the details of the app.
     *
     * @param string $name
     */
    public function setComposerFile()
    {
        $appNamespace = strtolower($this->app_name).'/'.strtolower($this->app_name);

        $composer = file_get_contents($this->directory.'/composer.json');
        $composer = str_replace('laravel/laravel', $appNamespace, $composer);

        if (file_put_contents($this->directory.'/composer.json', $composer)) {
            passthru('composer require yab/quarx -d='.$this->directory);
            return true;
        }

        return false;
    }

    public function setConfig()
    {
        $file = $this->directory.'/config/app.php';

        $config = file_get_contents($file);
        $config = str_replace('Laravel\Tinker\TinkerServiceProvider::class,', "Laravel\Tinker\TinkerServiceProvider::class,\n\t\tYab\Quarx\QuarxProvider::class,", $config);
        $config = str_replace('// App\Providers\BroadcastServiceProvider::class,', "App\Providers\BroadcastServiceProvider::class,", $config);

        return file_put_contents($file, $config);
    }

    /**
     * Run composer install.
     */
    public function composerInstall()
    {
        passthru('composer install -d='.$this->directory);
    }

    /**
     * Vendor publish.
     */
    public function vendorPublish()
    {
        passthru('php '.$this->directory.'/artisan vendor:publish');
    }


    /**
     * Vendor publish.
     */
    public function startQuarx()
    {
        passthru('cd '.$this->directory.' && php artisan quarx:setup');
        passthru('cd '.$this->directory.' && php artisan config:clear');
    }

    /**
     * Update the Readme.
     */
    public function updateTheReadMe()
    {
        $content = "#".$this->app_name."\n\nYour app's readme!";
        file_put_contents($this->directory.'/.readme.md', $content);
    }

    public function envCleanup()
    {
        $localEnv = file_get_contents(__DIR__.'/../../.env');
        $localEnv = str_replace('DB_DATABASE='.strtolower($this->app_name), 'DB_DATABASE=homestead', $localEnv);
        $localEnv = str_replace('DB_HOST='.$this->ip, 'DB_HOST=127.0.0.1', $localEnv);
        file_put_contents(__DIR__.'/../../.env', $localEnv);
    }

    /**
     * Update the database.
     */
    public function makeDatabase()
    {
        $env = file_get_contents($this->directory.'/.env');
        $env = str_replace('DB_DATABASE=homestead', 'DB_DATABASE='.strtolower($this->app_name), $env);
        $env = str_replace('DB_HOST=127.0.0.1', 'DB_HOST='.$this->ip, $env);
        file_put_contents($this->directory.'/.env', $env);
        passthru('composer dump -d='.$this->directory);

        $localEnv = file_get_contents(__DIR__.'/../../.env');
        $localEnv = str_replace('DB_DATABASE=homestead', 'DB_DATABASE='.strtolower($this->app_name), $localEnv);
        $localEnv = str_replace('DB_HOST=127.0.0.1', 'DB_HOST='.$this->ip, $localEnv);
        file_put_contents(__DIR__.'/../../.env', $localEnv);

        passthru('php '.$this->directory.'/artisan config:cache');
    }
}
