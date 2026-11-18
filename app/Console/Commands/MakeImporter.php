<?php

namespace App\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:importer')]
class MakeImporter extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $name = 'make:importer';

    /**
     * @var string
     */
    protected $description = 'Create an importer for a new statement source, or a new layout of an existing one';

    /**
     * @var string
     */
    protected $type = 'Importer';

    protected function getStub(): string
    {
        return base_path('stubs/importer.stub');
    }

    /**
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Services\Imports\Importers';
    }

    /**
     * @param  string  $name
     */
    protected function buildClass($name): string
    {
        $base = Str::of(class_basename($name))->beforeLast('Importer');
        $source = (string) ($this->option('source') ?: $base->kebab());
        $version = $this->option('layout');
        $key = $version ? $source.'-'.Str::slug($version) : $source;

        return str_replace(
            ['{{ key }}', '{{ source }}', '{{ sourceName }}', '{{ version }}', '{{ versionNote }}'],
            [
                $key,
                $source,
                (string) ($this->option('source') ? Str::headline($source) : $base->headline()),
                $version ? "'".addslashes($version)."'" : 'null',
                $version ? ", layout \"{$version}\"" : '',
            ],
            parent::buildClass($name),
        );
    }

    /**
     * @return list<array<int, mixed>>
     */
    protected function getOptions(): array
    {
        return [
            ['source', null, InputOption::VALUE_REQUIRED, 'Key of the existing source this is a new layout of, e.g. zebralution'],
            ['layout', null, InputOption::VALUE_REQUIRED, 'What distinguishes this layout, e.g. "2027 onwards"'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the importer already exists'],
        ];
    }
}
