<?php //>

namespace MatrixPlatform\Console\Commands;

use Illuminate\Console\Command;
use MatrixPlatform\Support\MetadataRegistry;
use MatrixPlatform\Support\PackageRegistry;
use MatrixPlatform\Support\Scaffold\FieldLabelResolver;
use MatrixPlatform\Support\Scaffold\FileWriter;
use MatrixPlatform\Support\Scaffold\PresentationGuesser;
use MatrixPlatform\Support\Scaffold\ScaffoldPlan;
use MatrixPlatform\Support\Scaffold\SchemaIntrospector;
use MatrixPlatform\Support\Scaffold\StubRenderer;
use MatrixPlatform\Support\Subject;
use RuntimeException;

class MakeCrudCommand extends Command {

    protected $description = 'Generate a draft Model, Declaration, Controller and i18n model file for a CRUD resource from an existing database table';

    protected $signature = 'matrix:make-crud {table} {--model=} {--title=} {--parent=} {--alias=} {--namespace=App} {--path=} {--locale=*} {--force} {--dry-run}';

    public function handle(): int {
        $table = strval(array_get_value($this->arguments(), 'table'));
        $introspector = app(SchemaIntrospector::class);

        if (!$introspector->tableExists($table)) {
            $this->error("table-not-found: '{$table}' does not exist in the 'public' schema");

            return self::FAILURE;
        }

        $namespace = $this->optionString('namespace');

        try {
            $plan = ScaffoldPlan::build(
                $introspector,
                app(PresentationGuesser::class),
                app(MetadataRegistry::class),
                app(PackageRegistry::class),
                app(Subject::class),
                $table,
                $this->optionString('model'),
                $this->optionString('title'),
                $this->optionString('parent'),
                $this->optionString('alias'),
                $namespace === null ? 'App' : $namespace
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $path = $this->optionString('path');
        $root = rtrim($path === null ? base_path() : $path, '/');
        $force = (bool) array_get_value($this->options(), 'force');
        $dryRun = (bool) array_get_value($this->options(), 'dry-run');
        $renderer = app(StubRenderer::class);
        $writer = app(FileWriter::class);
        $stubs = dirname(__DIR__, 3) . '/resources/stubs/scaffold';

        $targets = [
            ["{$root}/app/Models/{$plan->modelName}.php", $renderer->render("{$stubs}/model.stub", $plan->modelTokens())],
            ["{$root}/app/Models/Declarations/{$plan->declarationName}.php", $renderer->render("{$stubs}/declaration.stub", $plan->declarationTokens())],
            ["{$root}/app/Http/Controllers/Admin/{$plan->modelName}Controller.php", $renderer->render("{$stubs}/controller.stub", $plan->controllerTokens())]
        ];
        $resolver = app(FieldLabelResolver::class);
        $translationNotes = [];

        foreach ($this->targetLocales() as $locale) {
            $resolved = $resolver->resolve($plan, $locale);
            $targets[] = ["{$root}/resources/i18n/{$locale}/model/{$plan->table}.php", $renderer->render("{$stubs}/model-i18n.stub", $plan->modelI18nTokens($resolved['labels']))];
            array_push($translationNotes, ...$resolved['notes']);
        }

        foreach ($targets as [$targetPath, $content]) {
            $this->emit($writer, $targetPath, $content, $force, $dryRun);
        }

        $this->printPendingNotes($plan, $translationNotes);
        $this->printRouteSnippet($plan);
        $this->printMenuSnippet($plan);
        $this->printMenuI18nSnippet($plan);

        return self::SUCCESS;
    }

    private function emit(FileWriter $writer, string $path, string $content, bool $force, bool $dryRun): void {
        if ($dryRun) {
            $this->line("--- {$path} (dry-run, not written) ---");
            $this->line($content);

            return;
        }

        if ($writer->exists($path) && !$force) {
            $this->warn("Already exists, not overwritten (pass --force to overwrite): {$path}");
            $this->line($content);

            return;
        }

        $writer->write($path, $content, $force);
        $this->info("Written: {$path}");
    }

    private function optionString(string $name): ?string {
        $value = array_get_value($this->options(), $name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function printMenuI18nSnippet(ScaffoldPlan $plan): void {
        $this->newLine();
        $this->line('Add to resources/i18n/{locale}/menu/*.php (one block per locale):');

        foreach ($this->targetLocales() as $locale) {
            $this->line("[{$locale}]");
            $this->line("    '{$plan->path}' => 'TODO: {$plan->modelName} list title',");
            $this->line("    '{$plan->path}/{id}' => 'TODO: Edit {$plan->modelName}',");
            $this->line("    '{$plan->path}/new' => 'TODO: New {$plan->modelName}',");
        }
    }

    private function printMenuSnippet(ScaffoldPlan $plan): void {
        $this->newLine();
        $this->line('Add to resources/menu/*.php (pick the real parent category, icon and ranking):');

        $parentKey = $plan->parent === null ? "'TODO-parent-menu-key'" : "'{$plan->parent['alias']}'";
        $group = ["'icon' => 'fa-solid fa-TODO'", "'parent' => {$parentKey}"];

        if ($plan->parent === null && $plan->sortable) {
            $group[] = "'ranking' => 100";
        }

        $group[] = "'group' => true";
        $group[] = "'tag' => 'query'";

        $this->line("'{$plan->path}' => [" . implode(', ', $group) . '],');
        $this->newLine();
        $this->line("    '{$plan->path}/{id}' => ['parent' => '{$plan->path}', 'tag' => 'query'],");
        $this->line("    '{$plan->path}/{id}/update' => ['parent' => '{$plan->path}', 'tag' => 'update'],");

        if ($plan->arrangeable) {
            $this->line("    '{$plan->path}/arrange' => ['parent' => '{$plan->path}', 'tag' => 'update'],");
            $this->line("    '{$plan->path}/arrange/save' => ['parent' => '{$plan->path}', 'tag' => 'update'],");
        } elseif ($plan->sortable) {
            $this->line("    '{$plan->path}/sort' => ['parent' => '{$plan->path}', 'tag' => 'update'],");
            $this->line("    '{$plan->path}/sort/save' => ['parent' => '{$plan->path}', 'tag' => 'update'],");
        }

        $this->line("    '{$plan->path}/delete' => ['parent' => '{$plan->path}', 'tag' => 'delete'],");
        $this->line("    '{$plan->path}/insert' => ['parent' => '{$plan->path}', 'tag' => 'insert'],");
        $this->line("    '{$plan->path}/new' => ['parent' => '{$plan->path}', 'tag' => 'insert'],");
    }

    /**
     * @param list<string> $extraNotes
     */
    private function printPendingNotes(ScaffoldPlan $plan, array $extraNotes = []): void {
        $notes = [...$plan->pendingNotes, ...$extraNotes];

        if ($notes === []) {
            return;
        }

        $this->newLine();
        $this->warn('Please review before committing:');

        foreach ($notes as $note) {
            $this->line("  - {$note}");
        }
    }

    private function printRouteSnippet(ScaffoldPlan $plan): void {
        $this->newLine();
        $this->line('Add to routes/*.php (inside the innermost middleware group, alongside the other permission-protected routes):');
        $this->line("use {$plan->namespace}\\Http\\Controllers\\Admin\\{$plan->modelName}Controller;");
        $this->line("ActionRoutes::mount('{$plan->path}', {$plan->modelName}Controller::class);");
    }

    /**
     * @return list<string>
     */
    private function targetLocales(): array {
        $option = array_get_value($this->options(), 'locale');
        $requested = is_array($option) ? array_values(array_filter($option, 'is_string')) : [];

        return $requested === [] ? locales() : $requested;
    }

}
