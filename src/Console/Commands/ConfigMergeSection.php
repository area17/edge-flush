<?php

namespace A17\EdgeFlush\Console\Commands;

use A17\EdgeFlush\EdgeFlush;
use Illuminate\Console\Command;
use A17\EdgeFlush\Exceptions\PackageException;

class ConfigMergeSection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'edge-flush:config:merge {section}';

    /**
     * The console command description.
     *
     * @var null|string
     */
    protected $description = 'Merge a section into the published config file';

    /**
     * The section file.
     *
     * @var string
     */
    protected string $sectionFile = '';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("Merging section {$this->getSection()} into the published config file...");

        try {
            $this->checkSectionFileExists();

            $this->checkPublishedConfigFileExists();

            $this->checkSectionIsMissinfFromConfig();

            $this->mergeSection();

            $this->info('Merged.');
        } catch (PackageException $e) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    public function checkSectionFileExists(): void
    {
        $section = $this->getSection();

        $file = realpath(config('edge-flush.package.path') . "{$section}.php");

        if (is_string($file) && file_exists($file)) {
            $this->sectionFile = $file;

            return;
        }

        $this->throw($message = "The section file '{$this->sectionFile}' does not exist.");
    }

    public function checkPublishedConfigFileExists(): void
    {
        $isPublished = realpath($fileName = config_path(EdgeFlush::packageName() . '.php'));

        if ($isPublished === false) {
            $this->throw(
                "The published config file '{$fileName}' does not exist. Did you forget to publish the config file?",
            );
        }
    }

    /**
     * @throws \Exception
     */
    public function throw(string $message): void
    {
        $this->error($message);

        throw new PackageException($message);
    }

    public function checkSectionIsMissinfFromConfig(): void
    {
        $section = $this->getSection();

        $publishedConfigFile = config_path(EdgeFlush::packageName() . '.php');

        $publishedConfig = require $publishedConfigFile;

        if (isset($publishedConfig[$section])) {
            $this->throw(
                "The section '{$section}' is already present in the published config file '{$publishedConfigFile}'.",
            );
        }
    }

    public function mergeSection(): void
    {
        $file = config_path(EdgeFlush::packageName() . '.php');

        $section = $this->getSection();

        $publishedConfigContents = file_get_contents($file);

        if ($publishedConfigContents === false) {
            $this->throw("Could not read the published config file '{$file}'.");

            return;
        }

        $publishedConfig = require $file;

        if (!is_array($publishedConfig)) {
            $this->throw('The current config file has an error.');
        }

        $newSection = $this->extractInternalArray(file_get_contents($this->sectionFile));

        if (strpos($publishedConfigContents, $endOfArray = "];\n") === false) {
            $this->throw("The published config file array doesn't end properly with '];'.");
        }

        $publishedConfigContents = str_replace(
            $endOfArray,
            "    {$newSection},\n];\n",
            $publishedConfigContents,
        );

        $publishedConfigContents = str_replace(
            ',,',
            ',',
            $publishedConfigContents,
        );

        file_put_contents($updatedFile = "$file.updated", $publishedConfigContents);

        $updated = require $updatedFile;

        if (!is_array($updated)) {
            $this->throw('There was an error trying to update the confi file.');
        }

        foreach ($publishedConfig as $key => $value) {
            if (($updated[$key] ?? null) !== $value) {
                $this->throw('It was not possible to update the config file correctly.');
            }
        }

        file_put_contents($file, $publishedConfigContents);

        unlink($updatedFile);
    }

    public function extractInternalArray(string|false|null $content): string|null
    {
        if ($content === false || blank($content)) {
            $this->throw('The section file is empty.');

            return null;
        }

        $lines = explode("\n", $content);

        $result = [];

        $beforeReturn = true;

        foreach ($lines as $key => $line) {
            $line = str_replace('<?php', '', $line);

            $beforeReturn = $beforeReturn && !str_contains($line, 'return');

            $line = str_replace('return ', '', $line);

            $line = str_replace(';', '', $line);

            if ($beforeReturn) {
                if (blank(trim($line))) {
                    continue;
                }
            }

            $result[] = $line;
        }

        if (trim($result[0]) === '[') {
            unset($result[0]);
        }

        if (trim($result[$pos = count($result) - 1]) === ']') {
            unset($result[$pos]);
        }

        return trim(implode("\n", $result));
    }

    public function getSection(): string
    {
        $section = $this->argument('section');

        if (is_string($section)) {
            return $section;
        }

        return '';
    }
}
