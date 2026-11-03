<?php

namespace App\Doctrine;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

// REPLACE(texte, cherché, remplacement) de MariaDB en DQL, que Doctrine ne fournit pas (déclarée dans doctrine.yaml).
// Sert à comparer un téléphone sans ses séparateurs (CustomerRepository).
final class ReplaceFunction extends FunctionNode
{
    /** @var Node[] */
    private array $arguments = [];

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->arguments[] = $parser->StringPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->arguments[] = $parser->StringPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->arguments[] = $parser->StringPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return 'REPLACE(' . implode(', ', array_map($sqlWalker->walkStringPrimary(...), $this->arguments)) . ')';
    }
}
