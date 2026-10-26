<?php

namespace App\Enums;

enum ProgrammingLanguageEnum: string
{
    case TXT = 'txt';
    case PHP = 'php';
    case HTML = 'html';
    case XML = 'xml';
    case CSS = 'css';
    case JS = 'javascript';
    case JSON = 'json';
    case PYTHON = 'python';
    case SQL = 'sql';
    case BASH = 'bash';
    case JAVA = 'java';
    case CSHARP = 'csharp';
    case GO = 'go';
    case MARKDOWN = 'markdown';

    public function label(): string
    {
        return match ($this) {
            self::TXT => 'Plain text',
            self::PHP => 'PHP',
            self::HTML => 'HTML',
            self::XML => 'XML',
            self::CSS => 'CSS',
            self::JS => 'JavaScript',
            self::JSON => 'JSON',
            self::PYTHON => 'Python',
            self::SQL => 'SQL',
            self::BASH => 'Bash',
            self::JAVA => 'Java',
            self::CSHARP => 'C#',
            self::GO => 'Go',
            self::MARKDOWN => 'Markdown',
        };
    }

    /** Name of the highlight.js grammar, or null for plain text. */
    public function highlightName(): ?string
    {
        return match ($this) {
            self::TXT => null,
            self::HTML => 'xml',
            default => $this->value,
        };
    }
}
