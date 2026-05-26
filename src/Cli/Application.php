<?php

declare(strict_types=1);

namespace VendingMachine\Cli;

final class Application
{
    public function __construct(private readonly CommandProcessor $commandProcessor)
    {
    }

    /**
     * @param resource $input
     * @param resource $output
     */
    public function run($input, $output): int
    {
        fwrite($output, "Vending Machine CLI\n");
        fwrite($output, "Type \"HELP\" for available commands.\n\n");

        while (!$this->commandProcessor->shouldExit()) {
            fwrite($output, '> ');

            $line = fgets($input);

            if ($line === false) {
                fwrite($output, "Bye.\n");

                return 0;
            }

            fwrite($output, $this->commandProcessor->handle($line) . "\n\n");
        }

        return 0;
    }
}
