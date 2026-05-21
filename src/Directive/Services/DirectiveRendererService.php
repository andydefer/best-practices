<?php

declare(strict_types=1);

namespace AndyDefer\BestPractices\Directive\Services;

use AndyDefer\BestPractices\Collections\TypedRecords;

class DirectiveRendererService
{
    public function renderHelp(): string
    {
        return <<<HELP

\033[36m═══════════════════════════════════════════════════════════════════════════\033[0m
\033[1;33m🎯 Directive System - Command Line Interface\033[0m
\033[36m═══════════════════════════════════════════════════════════════════════════\033[0m

\033[1;32mUSAGE:\033[0m
  directive <signature> [arguments] [options]

\033[1;32mCOMMANDS:\033[0m
  \033[33m--list, -l\033[0m      List all available directives
  \033[33m--help, -h\033[0m      Show this help message

\033[1;32mEXAMPLES:\033[0m
  \033[36m# Run a simple directive\033[0m
  directive hello

  \033[36m# Run with arguments\033[0m
  directive user:create John Doe --role=admin

  \033[36m# Run with flags\033[0m
  directive cache:clear --force

  \033[36m# List all directives\033[0m
  directive --list

\033[1;32mCREATE YOUR OWN DIRECTIVE:\033[0m
  1. Create a file in \033[33mapp/Directives/\033[0m
  2. Extend \033[33mAbstractDirective\033[0m
  3. Implement \033[33mgetSignature()\033[0m, \033[33mgetDescription()\033[0m and \033[33mexecute()\033[0m

\033[1;32mFOR MORE INFORMATION:\033[0m
  Documentation: https://github.com/andy-defer/best-practices

\033[36m═══════════════════════════════════════════════════════════════════════════\033[0m

HELP;
    }

    public function renderList(TypedRecords $directives): string
    {
        if ($directives->isEmpty()) {
            return $this->renderEmptyDirectivesMessage();
        }

        $lines = [
            "\n\033[36m═══════════════════════════════════════════════════════════════════════════\033[0m",
            "\033[1;32m✅ Available Directives (".$directives->count().")\033[0m",
            "\033[36m═══════════════════════════════════════════════════════════════════════════\033[0m",
            sprintf("\033[1;37m%-25s \033[1;36m%s\033[0m", 'Signature', 'Description'),
            "\033[90m".str_repeat('─', 70)."\033[0m",
        ];

        foreach ($directives as $directive) {
            $aliases = $directive->aliases->count() > 0
                ? ' ('.implode(', ', $directive->aliases->toArray()).')'
                : '';

            $lines[] = sprintf(
                "  \033[33m%-23s\033[0m \033[37m%s\033[90m%s\033[0m",
                $directive->signature,
                $directive->description,
                $aliases
            );
        }

        $lines[] = "\033[36m═══════════════════════════════════════════════════════════════════════════\033[0m";
        $lines[] = "\n\033[90m💡 Usage: directive <signature> [arguments] [--options]\033[0m";
        $lines[] = "\033[90m📚 Run 'directive --help' for more information\033[0m\n";

        return implode("\n", $lines);
    }

    private function renderEmptyDirectivesMessage(): string
    {
        return <<<EMPTY

\033[36m═══════════════════════════════════════════════════════════════════════════\033[0m
\033[1;33m⚠️  No Directives Found\033[0m
\033[36m═══════════════════════════════════════════════════════════════════════════\033[0m

\033[37mLet's create your first directive!\033[0m

\033[1;32m📁 Create the directory:\033[0m
  mkdir -p app/Directives

\033[1;32m📝 Create a file \033[33mapp/Directives/HelloDirective.php\033[0m

\033[1;32m🚀 Run your directive:\033[0m
  php directive hello "John Doe"

\033[90m💡 Tip: Run 'directive --list' after creating your directive to see it here!\033[0m

\033[36m═══════════════════════════════════════════════════════════════════════════\033[0m

EMPTY;
    }

    public function renderNotFound(string $signature): string
    {
        return <<<NOTFOUND

\033[31m✗ Directive '\033[1;33m{$signature}\033[0m\033[31m' not found\033[0m

\033[90m💡 Suggestions:\033[0m
  • Run \033[33mdirective --list\033[0m to see available directives
  • Check the spelling of the directive name
  • Make sure the directive file exists in \033[33mapp/Directives/\033[0m
  • Run \033[33mcomposer dump-autoload\033[0m if you just added a new directive

NOTFOUND;
    }
}
