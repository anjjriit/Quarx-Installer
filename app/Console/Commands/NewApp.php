<?php

namespace QuarxInstaller\Console\Commands;

use QuarxInstaller\Services\NewAppService;
use Illuminate\Console\Command;

class NewApp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'new {name} {--ip=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a starter app with Quarx';

    /**
     * Construct.
     *
     * @param QuarxInstaller $service
     */
    public function __construct(NewAppService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $name = $this->argument('name');
        $ip = $this->option('ip');

        try {
            if ($this->confirm('Do you currently have PHP v5.6.4 or greater installed? Along with Composer and MySQL or SQLite?')) {
                $this->service->handle($name, $ip);
                $this->info($name.' has been created.');
                $this->comment('You can now change to the following directory:');
                $this->info(getcwd().'/'.$name);
                $this->comment('And in your app you can log in with:');
                $this->info('admin@admin.com');
                $this->info('admin');
            } else {
                $this->comment('Please install the following:');
                $this->line('PHP >= v5.6.4');
                $this->line('Composer >= v1.0.0');
                $this->line('MySQL >= v5.6.0 or SQLite >= v3.0.0');
            }
        } catch (Exception $e) {
            $this->debug($e->getMessage());
        }
    }
}
