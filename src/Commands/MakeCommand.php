<?php

namespace Yab\QuarxInstaller\Commands;

use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('new')
            ->setDescription('Create a new Quarx based project')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the project')
            ->addArgument('ip', InputArgument::OPTIONAL, 'The IP of your database');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');

        $this->ip = $input->getArgument('ip');
        $this->directory = getcwd().'/'.$name;
        $this->app_name = $name;
        $this->fileSystem = new Filesystem();

        if (is_dir(getcwd().'/'.$this->app_name)) {
            throw new \Exception("This $this->app_name directory already exists", 1);
        }

        exec('composer create-project laravel/laravel '.$this->directory);

        if ($this->setUpApp()) {
            $output->writeln('<info>Your Quarx is now prepared at: '.$this->directory.'</info>');
            $output->writeln('<info>If you migrated you\'re good to go, if not you\'ll need to run that.</info>');
            $output->writeln('<info>You will also have to run: npm install & npm run dev</info>');
        }

        return false;
    }

    /**
     * Set up the app.
     */
    public function setUpApp()
    {
        if ($this->setComposerFile()) {
            $this->fileSystem->remove($this->directory.'/.git');
            $this->composerInstall();
            $this->setConfig();
            $this->vendorPublish();
            $this->makeDatabase();
            $this->startQuarx();
            $this->updateTheReadMe();
            $this->envCleanup();
        }

        return true;
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

        unlink($this->directory.'/app/User.php');

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
     * Start Quarx.
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
