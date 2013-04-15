<?php namespace Way\Generators\Commands;

use Way\Generators\Generators\ResourceGenerator;
use Way\Generators\Cache;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Illuminate\Support\Pluralizer;

class MissingFieldsException extends \Exception {}

class ResourceGeneratorCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'generate:resource';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a resource.';

    /**
     * Model generator instance
     *
     * @var Way\Generators\Generators\ResourceGenerator
     */
    protected $generator;

    /**
     * File cache
     *
     * @var Cache
     */
    protected $cache;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(ResourceGenerator $generator, Cache $cache)
    {
        parent::__construct();

        $this->generator = $generator;
        $this->cache = $cache;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        // Scaffolding should always begin with the singular
        // form of the now.
        $this->model = Pluralizer::singular($this->argument('name'));

        $this->fields = $this->option('fields');

        if (is_null($this->fields))
        {
            throw new MissingFieldsException('You must specify the fields option.');
        }

        // We're going to need access to these values
        // within future commands. I'll save them
        // to temporary files to allow for that.
        $this->cache->fields($this->fields);
        $this->cache->modelName($this->model);

        $this->generateModel();
        $this->generateController();
        $this->generateViews();
        $this->generateMigration();
        $this->generateSeed();

        $this->generator->updateRoutesFile($this->model);
        $this->info('Updated app/routes.php');

        // We're all finished, so we
        // can delete the cache.
        $this->cache->destroyAll();
    }

    /**
     * Get the path to the template for a model
     *
     * @return string
     */
    protected function getModelTemplatePath()
    {
        return __DIR__.'/../Generators/templates/model.txt';
    }

    /**
     * Get the path to the template for a controller
     *
     * @return string
     */
    protected function getControllerTemplatePath()
    {
        return __DIR__.'/../Generators/templates/controller.txt';
    }

    /**
     * Get the path to the template for a view
     *
     * @return string
     */
    protected function getViewTemplatePath($view = 'view')
    {
        return __DIR__."/../Generators/templates/view.txt";
    }

    /**
     * Call generate:model
     *
     * @return void
     */
    protected function generateModel()
    {
        // For now, this is just the regular model template
        $this->call(
            'generate:model',
            array(
                'name' => $this->model,
                '--template' => $this->getModelTemplatePath()
            )
        );
    }

    /**
     * Call generate:controller
     *
     * @return void
     */
   protected function generateController()
    {
        $this->call(
            'generate:controller',
            array(
                'name' => "{$this->model}sController", // todo
                '--template' => $this->getControllerTemplatePath()
            )
        );
    }

    /**
     * Call generate:views
     *
     * @return void
     */
    protected function generateViews()
    {
        $viewsDir = app_path().'/views';
        $container = $viewsDir . '/' . Pluralizer::plural($this->model);
        $layouts = $viewsDir . '/layouts';

        $this->generator->folders(
            array(
                $container,
                $layouts
            )
        );

        // Let's filter through all of our needed views
        // and create each one.
        foreach(array('master', 'index', 'show', 'create', 'edit') as $view)
        {
            $path = $view === 'master' ? $layouts : $container;
            $this->generateView($view, $path);
        }
    }

    /**
     * Generate a view
     *
     * @param  string $view
     * @param  string $path
     * @return void
     */
    protected function generateView($view, $path)
    {
        $this->call(
            'generate:view',
            array(
                'name'       => $view,
                '--path'     => $path,
                '--template' => $this->getViewTemplatePath($view)
            )
        );
    }

    /**
     * Call generate:migration
     *
     * @return void
     */
    protected function generateMigration()
    {
        $this->call(
            'generate:migration',
            array(
                'name'      => "create_{$this->model}s_table",
                '--fields'  => $this->option('fields')
            )
        );
    }

    protected function generateSeed()
    {
        $this->call(
            'generate:seed',
            array(
                'name' => Pluralizer::plural(strtolower($this->model))
            )
        );
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('name', InputArgument::REQUIRED, 'Name of the desired resource.'),
        );
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('path', null, InputOption::VALUE_OPTIONAL, 'The path to the migrations folder', 'app/database/migrations'),
            array('fields', null, InputOption::VALUE_OPTIONAL, 'Table fields', null)
        );
    }

}