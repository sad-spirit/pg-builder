<?php

/*
 * This file is part of sad_spirit/pg_builder:
 * query builder for Postgres backed by SQL parser
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

// phpcs:disable PSR1.Methods.CamelCapsMethodName
/**
 * Recursive descent parser for PostgreSQL's dialect of SQL
 *
 * Parses an SQL query converted to TokenStream by Lexer, returns the syntax tree for it.
 *
 * The parsing methods are named parse*() and dispatched through __call() due to
 * amount of boilerplate code needed. The main method is parseStatement(), several
 * more are available to facilitate parsing of expression parts rather than complete
 * SQL statements.
 *
 * @method Statement                        parseStatement($input)
 * @method SelectCommon                     parseSelectStatement($input)
 * @method nodes\lists\ExpressionList       parseExpressionList($input)
 * @method nodes\ScalarExpression           parseExpression($input)
 * @method nodes\expressions\RowExpression  parseRowConstructor($input)
 * @method nodes\expressions\RowExpression  parseRowConstructorNoKeyword($input)
 * @method nodes\lists\RowList              parseRowList($input)
 * @method nodes\lists\TargetList           parseTargetList($input)
 * @method nodes\TargetElement              parseTargetElement($input)
 * @method nodes\lists\FromList             parseFromList($input)
 * @method nodes\range\FromElement          parseFromElement($input)
 * @method nodes\lists\OrderByList          parseOrderByList($input)
 * @method nodes\OrderByElement             parseOrderByElement($input)
 * @method nodes\lists\WindowList           parseWindowList($input)
 * @method nodes\WindowDefinition           parseWindowDefinition($input)
 * @method nodes\LockingElement             parseLockingElement($input)
 * @method nodes\lists\LockList             parseLockingList($input)
 * @method nodes\range\UpdateOrDeleteTarget parseRelationExpressionOptAlias($input)
 * @method nodes\range\InsertTarget         parseInsertTarget($input)
 * @method nodes\QualifiedName              parseQualifiedName($input)
 * @method nodes\lists\SetClauseList        parseSetClauseList($input)
 * @method nodes\SingleSetClause|nodes\MultipleSetClause parseSetClause($input)
 * @method nodes\lists\SetTargetList        parseInsertTargetList($input)
 * @method nodes\SetTargetElement           parseSetTargetElement($input)
 * @method nodes\ScalarExpression           parseExpressionWithDefault($input)
 * @method nodes\WithClause                 parseWithClause($input)
 * @method nodes\CommonTableExpression      parseCommonTableExpression($input)
 * @method nodes\lists\IdentifierList       parseColIdList($input)
 * @method nodes\range\UsingClause          parseUsingClause($input)
 * @method nodes\OnConflictClause           parseOnConflict($input)
 * @method nodes\IndexParameters            parseIndexParameters($input)
 * @method nodes\IndexElement               parseIndexElement($input)
 * @method nodes\group\GroupByClause        parseGroupByClause($input)
 * @method nodes\ScalarExpression|nodes\group\GroupByElement parseGroupByElement($input)
 * @method nodes\xml\XmlNamespaceList       parseXmlNamespaceList($input)
 * @method nodes\xml\XmlNamespace           parseXmlNamespace($input)
 * @method nodes\xml\XmlColumnList          parseXmlColumnList($input)
 * @method nodes\xml\XmlColumnDefinition    parseXmlColumnDefinition($input)
 * @method nodes\TypeName                   parseTypeName($input)
 * @method nodes\merge\MergeWhenList        parseMergeWhenList($input)
 * @method nodes\merge\MergeWhenClause      parseMergeWhenClause($input)
 */
class Parser
{
    /**
     * Mathematical operators
     *
     * mathOp production from gram.y
     */
    private const MATH_OPERATORS = ['+', '-', '*', '/', '%', '^', '<', '>', '=', '<=', '>=', '!=', '<>'];

    /**
     * Subquery expressions that can appear at right side of most scalar operators
     *
     * sub_type production from gram.y
     */
    private const SUBQUERY_EXPRESSIONS = [Keyword::ANY, Keyword::ALL, Keyword::SOME];

    /**
     * Known system functions that must appear with parentheses
     *
     * From func_expr_common_subexpr production in gram.y
     */
    private const SYSTEM_FUNCTIONS = [
        Keyword::CAST, Keyword::EXTRACT, Keyword::OVERLAY, Keyword::POSITION, Keyword::SUBSTRING, Keyword::TREAT,
        Keyword::TRIM, Keyword::NULLIF, Keyword::COALESCE, Keyword::GREATEST, Keyword::LEAST, Keyword::XMLCONCAT,
        Keyword::XMLELEMENT, Keyword::XMLEXISTS, Keyword::XMLFOREST, Keyword::XMLPARSE, Keyword::XMLPI,
        Keyword::XMLROOT, Keyword::XMLSERIALIZE, Keyword::NORMALIZE,
        // new JSON functions in Postgres 16+, json_func_expr production
        Keyword::JSON_OBJECT, Keyword::JSON_ARRAY, Keyword::JSON, Keyword::JSON_SCALAR, Keyword::JSON_SERIALIZE,
        Keyword::JSON_EXISTS, Keyword::JSON_VALUE, Keyword::JSON_QUERY,
        // function for RETURNING clause of MERGE, since Postgres 17
        Keyword::MERGE_ACTION
    ];

    /**
     * Returned by {@see checkContentsOfParentheses()} if subquery is found
     */
    private const PARENTHESES_SELECT     = 'select';

    /**
     * Returned by {@see checkContentsOfParentheses()} if row constructor (=expression list) is found
     */
    private const PARENTHESES_ROW        = 'row';

    /**
     * Returned by {@see checkContentsOfParentheses()} if parentheses contain a named function argument
     */
    private const PARENTHESES_ARGS       = 'args';

    /**
     * Returned by {@see checkContentsOfParentheses()} if parentheses contain a scalar expression
     */
    private const PARENTHESES_EXPRESSION = 'expression';

    /**
     * Passed to {@see UpdateOrDeleteTarget()} to set expected format, allows only relation alias
     */
    private const RELATION_FORMAT_UPDATE = 'update';

    /**
     * Passed to {@see UpdateOrDeleteTarget()} to set expected format, allows only relation alias (which can be SET)
     */
    private const RELATION_FORMAT_DELETE = 'delete';

    /**
     * Checks for SQL standard date and time type names
     */
    private const STANDARD_TYPES_DATETIME  = [Keyword::TIME, Keyword::TIMESTAMP];

    /**
     * Checks for SQL standard character type names
     */
    private const STANDARD_TYPES_CHARACTER = [
        Keyword::CHARACTER,
        Keyword::CHAR,
        Keyword::VARCHAR,
        Keyword::NCHAR,
        Keyword::NATIONAL
    ];

    /**
     * Checks for SQL standard bit type name(s)
     */
    private const STANDARD_TYPES_BIT       = Keyword::BIT;

    /**
     * Checks for SQL standard numeric type name(s)
     */
    private const STANDARD_TYPES_NUMERIC   = [
        Keyword::INT,
        Keyword::INTEGER,
        Keyword::SMALLINT,
        Keyword::BIGINT,
        Keyword::REAL,
        Keyword::FLOAT,
        Keyword::DECIMAL,
        Keyword::DEC,
        Keyword::NUMERIC,
        Keyword::BOOLEAN,
        Keyword::DOUBLE
    ];

    /**
     * Checks for SQL standard JSON type name
     */
    private const STANDARD_TYPES_JSON      = Keyword::JSON;

    /**
     * Two-word names for SQL standard types
     */
    private const STANDARD_DOUBLE_WORD_TYPES = [
        'double'   => [Keyword::PRECISION],
        'national' => [Keyword::CHARACTER, Keyword::CHAR]
    ];

    /**
     * SQL standard type names that can be followed by VARYING keyword
     */
    private const STANDARD_TYPES_OPT_VARYING = [
        'bit'       => true,
        'character' => true,
        'char'      => true,
        'nchar'     => true,
        'national'  => true
    ];

    /**
     * SQL standard type names that cannot have modifiers
     */
    private const STANDARD_TYPES_NO_MODIFIERS = [
        'int'       => true,
        'integer'   => true,
        'smallint'  => true,
        'bigint'    => true,
        'real'      => true,
        'boolean'   => true,
        'double'    => true
    ];

    /**
     * Mapping from SQL standard types to their equivalents in pg_catalog.pg_type
     */
    private const STANDARD_TYPES_MAPPING = [
        'int'              => 'int4',
        'integer'          => 'int4',
        'smallint'         => 'int2',
        'bigint'           => 'int8',
        'real'             => 'float4',
        'decimal'          => 'numeric',
        'dec'              => 'numeric',
        'numeric'          => 'numeric',
        'boolean'          => 'bool',
        'double precision' => 'float8'
    ];

    /**
     * Keyword sequence checks for {@see WindowFrameBound()} method
     */
    private const CHECKS_FRAME_BOUND = [
        [Keyword::UNBOUNDED, Keyword::PRECEDING],
        [Keyword::UNBOUNDED, Keyword::FOLLOWING],
        [Keyword::CURRENT, Keyword::ROW]
    ];

    /**
     * Keyword sequence checks for {@see PatternMatchingExpression()} method
     */
    private const CHECKS_PATTERN_MATCHING = [
        [Keyword::LIKE],
        [Keyword::NOT, Keyword::LIKE],
        [Keyword::ILIKE],
        [Keyword::NOT, Keyword::ILIKE],
        // the following cannot be applied to subquery operators
        [Keyword::SIMILAR, Keyword::TO],
        [Keyword::NOT, Keyword::SIMILAR, Keyword::TO]
    ];

    /**
     * Keyword sequence checks for {@see IsWhateverExpression()} method
     */
    private const CHECKS_IS_WHATEVER = [
        [Keyword::NULL],
        [Keyword::TRUE],
        [Keyword::FALSE],
        [Keyword::UNKNOWN],
        [Keyword::NORMALIZED],
        [[Keyword::NFC, Keyword::NFD, Keyword::NFKC, Keyword::NFKD], Keyword::NORMALIZED],
        [Keyword::JSON]
    ];

    /**
     * Keywords that can appear in {@see ExpressionAtom()} on their own right
     */
    private const ATOM_KEYWORDS = [
        Keyword::ROW, Keyword::ARRAY, Keyword::EXISTS, Keyword::CASE, Keyword::GROUPING,
        Keyword::TRUE, Keyword::FALSE, Keyword::NULL
    ];

    /**
     * A bit mask of Token types that are checked first in {@see ExpressionAtom()}
     */
    private const ATOM_SPECIAL_TYPES = TokenType::SPECIAL->value
        | TokenType::PARAMETER->value
        | TokenType::LITERAL->value;

    /**
     * Token types that can appear as the first part of an Identifier in {@see NamedExpressionAtom()}
     */
    private const ATOM_IDENTIFIER_TYPES = [
        TokenType::IDENTIFIER,
        TokenType::TYPE_FUNC_NAME_KEYWORD,
        TokenType::COL_NAME_KEYWORD,
        TokenType::UNRESERVED_KEYWORD
    ];

    /**
     * Methods that are exposed through __call()
     * @var array
     */
    private const CALLABLE = [
        'statement'                  => true,
        'selectstatement'            => true,
        'expression'                 => true,
        'expressionlist'             => true,
        'rowconstructor'             => true,
        'rowconstructornokeyword'    => true,
        'rowlist'                    => true,
        'targetlist'                 => true,
        'targetelement'              => true,
        'fromlist'                   => true,
        'fromelement'                => true,
        'windowlist'                 => true,
        'windowdefinition'           => true, // element of WindowList
        'orderbylist'                => true,
        'orderbyelement'             => true,
        'lockinglist'                => true,
        'lockingelement'             => true,
        'relationexpressionoptalias' => true,
        'inserttarget'               => true,
        'qualifiedname'              => true,
        'setclause'                  => true, // for UPDATE
        'setclauselist'              => true, // for UPDATE
        'settargetelement'           => true, // for INSERT
        'inserttargetlist'           => true, // for INSERT
        'expressionwithdefault'      => true,
        'withclause'                 => true,
        'commontableexpression'      => true,
        'colidlist'                  => true,
        'usingclause'                => true,
        'indexparameters'            => true,
        'indexelement'               => true,
        'onconflict'                 => true,
        'groupbyclause'              => true,
        'groupbyelement'             => true,
        'xmlnamespacelist'           => true,
        'xmlnamespace'               => true,
        'xmlcolumnlist'              => true,
        'xmlcolumndefinition'        => true,
        'typename'                   => true,
        'mergewhenlist'              => true,
        'mergewhenclause'            => true
    ];

    private TokenStream $stream;

    /**
     * Guesses the type of parenthesised expression
     *
     * Parentheses may contain
     *  * expressions: (foo + bar)
     *  * row constructors: (foo, bar)
     *  * subselects (select foo, bar)
     *
     * @param int $lookIdx Where to start looking for parentheses (defaults to current position)
     * @return null|string Either of 'select', 'row' or 'expression'. Null if stream was not on opening parenthesis
     * @throws exceptions\SyntaxException in case of unclosed parenthesis
     */
    private function checkContentsOfParentheses(int $lookIdx = 0): ?string
    {
        $openParens = [];
        while ($this->stream->look($lookIdx)->matches(TokenType::SPECIAL_CHAR, '(')) {
            $openParens[] = $lookIdx++;
        }
        if (0 === $lookIdx && [] === $openParens) {
            return null;
        }

        if (!$this->stream->look($lookIdx)->matchesAnyKeyword(Keyword::VALUES, Keyword::SELECT, Keyword::WITH)) {
            $selectLevel = false;
        } elseif (1 === ($selectLevel = \count($openParens))) {
            return self::PARENTHESES_SELECT;
        }

        do {
            $token = $this->stream->look(++$lookIdx);
            if ($token instanceof tokens\EOFToken) {
                break;
            } elseif (!$token instanceof tokens\StringToken) {
                continue;
            }

            switch ($token->getType()) {
                case TokenType::SPECIAL_CHAR:
                    switch ($token->getValue()) {
                        case '[':
                            $lookIdx = $this->skipParentheses($lookIdx, true) - 1;
                            break;

                        case '(':
                            $openParens[] = $lookIdx;
                            break;

                        case ',':
                            if (1 === \count($openParens) && !$selectLevel) {
                                return self::PARENTHESES_ROW;
                            }
                            break;

                        case ')':
                            if (1 < \count($openParens) && $selectLevel === \count($openParens)) {
                                if (
                                    $this->stream->look($lookIdx + 1)
                                        ->matchesAnyKeyword(
                                            Keyword::UNION,
                                            Keyword::INTERSECT,
                                            Keyword::EXCEPT,
                                            Keyword::ORDER,
                                            Keyword::LIMIT,
                                            Keyword::OFFSET,
                                            Keyword::FOR /* ...update */,
                                            Keyword::FETCH /* SQL:2008 limit */
                                        )
                                    || $this->stream->look($lookIdx + 1)
                                        ->matches(TokenType::SPECIAL_CHAR, ')')
                                ) {
                                    // this addresses stuff like ((select 1) order by 1)
                                    $selectLevel--;
                                } else {
                                    $selectLevel = false;
                                }
                            }
                            \array_pop($openParens);
                    }
                    break;

                case TokenType::COLON_EQUALS:
                case TokenType::EQUALS_GREATER:
                    if (1 === \count($openParens) && !$selectLevel) {
                        return self::PARENTHESES_ARGS;
                    }
            }
        } while ([] !== $openParens);

        if ([] !== $openParens) {
            $token = $this->stream->look(\array_shift($openParens));
            throw exceptions\SyntaxException::atPosition(
                "Unbalanced '('",
                $this->stream->getSource(),
                $token->getPosition()
            );
        }

        return $selectLevel ? self::PARENTHESES_SELECT : self::PARENTHESES_EXPRESSION;
    }

    /**
     * Skips the expression enclosed in parentheses
     *
     * @param int  $start  Starting lookahed position in token stream
     * @param bool $square Whether we are skipping square brackets [] rather than ()
     * @return int Position after the closing ']' or ')'
     * @throws exceptions\SyntaxException in case of unclosed parentheses
     */
    private function skipParentheses(int $start, bool $square = false): int
    {
        $lookIdx    = $start;
        $openParens = 1;

        do {
            $token = $this->stream->look(++$lookIdx);
            switch ($token->getType()) {
                case TokenType::SPECIAL_CHAR:
                    if ($token->getValue() === ($square ? '[' : '(')) {
                        $openParens++;
                    } elseif ($token->getValue() === ($square ? ']' : ')')) {
                        $openParens--;
                    }
                    break;
                case TokenType::EOF:
                    break 2;
            }
        } while ($openParens > 0);

        if (0 !== $openParens) {
            $token = $this->stream->look($start);
            throw exceptions\SyntaxException::atPosition(
                "Unbalanced '" . ($square ? '[' : '(') . "'",
                $this->stream->getSource(),
                $token->getPosition()
            );
        }

        return $lookIdx + 1;
    }


    /**
     * Tests whether current position of stream matches a (possibly schema-qualified) operator
     */
    private function matchesOperator(): bool
    {
        return $this->stream->matches(TokenType::OPERATOR)
               || Keyword::OPERATOR === $this->stream->getKeyword()
                  && $this->stream->look(1)->matches(TokenType::SPECIAL_CHAR, '(');
    }


    /**
     * Tests whether current position of stream matches 'func_name' production from PostgreSQL's grammar
     *
     * Actually func_name allows indirection via array subscripts and appearance of '*' in
     * name, these are only disallowed later in processing, we disallow these here.
     *
     * @return int|false position after func_name if matches, false if not
     */
    private function matchesFuncName(): false|int
    {
        $firstType = $this->stream->getCurrent()->getType();
        if (!\in_array($firstType, self::ATOM_IDENTIFIER_TYPES, true)) {
            return false;
        }
        $idx = 1;
        while (
            $this->stream->look($idx)->matches(TokenType::SPECIAL_CHAR, '.')
            && ($this->stream->look($idx + 1)->matches(TokenType::IDENTIFIER)
                || $this->stream->look($idx + 1)->matches(TokenType::KEYWORD))
        ) {
            $idx += 2;
        }
        if (
            TokenType::TYPE_FUNC_NAME_KEYWORD === $firstType && 1 < $idx
            || TokenType::COL_NAME_KEYWORD === $firstType && 1 === $idx
        ) {
            // does not match func_name production
            return false;
        } else {
            return $idx;
        }
    }

    /**
     * Tests whether current position of stream matches a system function call
     */
    private function matchesSpecialFunctionCall(): bool
    {
        static $dontCheckParens = null;
        static $allNames = null;

        if (null === $dontCheckParens) {
            $dontCheckParens = enums\SQLValueFunctionName::toKeywords();
            $allNames = \array_merge(
                $dontCheckParens,
                self::SYSTEM_FUNCTIONS,
                [Keyword::COLLATION, Keyword::JSON_OBJECTAGG, Keyword::JSON_ARRAYAGG]
            );
        }

        if (null === $keyword = $this->stream->matchesAnyKeyword(...$allNames)) {
            return false;
        } elseif (\in_array($keyword, $dontCheckParens, true)) {
            return true;
        } elseif (Keyword::COLLATION === $keyword) {
            return Keyword::FOR === $this->stream->look()->getKeyword()
                && $this->stream->look(2)->matches(TokenType::SPECIAL_CHAR, '(');
        } else {
            return $this->stream->look()->matches(TokenType::SPECIAL_CHAR, '(');
        }
    }

    /**
     * Tests whether current position of stream matches a function call
     */
    private function matchesFunctionCall(): bool
    {
        return $this->matchesSpecialFunctionCall()
               || false !== ($idx = $this->matchesFuncName())
                  && $this->stream->look($idx)->matches(TokenType::SPECIAL_CHAR, '(');
    }

    /**
     * Tests whether current position of stream looks like a type cast with standard type name
     *
     * i.e. "typename 'string constant'" where typename is SQL standard one: "integer" but not "int4"
     */
    private function matchesConstTypecast(): bool
    {
        static $constNames       = null;
        static $trailingTimezone = null;

        if (null === $constNames) {
            $constNames = \array_merge(
                self::STANDARD_TYPES_CHARACTER,
                self::STANDARD_TYPES_NUMERIC,
                self::STANDARD_TYPES_DATETIME,
                [self::STANDARD_TYPES_BIT, self::STANDARD_TYPES_JSON, Keyword::INTERVAL]
            );
            $trailingTimezone = \array_flip(\array_map(
                fn (Keyword $keyword): string => $keyword->value,
                self::STANDARD_TYPES_DATETIME
            ));
        }

        if (null === $base = $this->stream->matchesAnyKeyword(...$constNames)) {
            return false;
        }

        $idx  = 1;
        if (
            isset(self::STANDARD_DOUBLE_WORD_TYPES[$base->value])
            && !$this->stream->look($idx++)->matchesAnyKeyword(...self::STANDARD_DOUBLE_WORD_TYPES[$base->value])
        ) {
            return false;
        }

        if (
            isset(self::STANDARD_TYPES_OPT_VARYING[$base->value])
            && Keyword::VARYING === $this->stream->look($idx)->getKeyword()
        ) {
            $idx++;
        }

        if (
            !isset(self::STANDARD_TYPES_NO_MODIFIERS[$base->value])
            && $this->stream->look($idx)->matches(TokenType::SPECIAL_CHAR, '(')
        ) {
            $idx = $this->skipParentheses($idx);
        }

        if (
            isset($trailingTimezone[$base->value])
            && $this->stream->look($idx)->matchesAnyKeyword(Keyword::WITH, Keyword::WITHOUT)
        ) {
            $idx += 3;
        }

        return $this->stream->look($idx)->matches(TokenType::STRING);
    }

    /**
     * Checks whether the given token matches the end of TargetElement
     *
     * This is needed to check whether some bare-label keywords are used as part of an Expression
     * or as column aliases in TargetElement
     *
     * Stuff that may legitimately follow TargetElement is
     *  - End of input (`RETURNING` clause is always the last one, `SELECT` may contain only TargetList)
     *  - Closing parenthesis, see above
     *  - A comma (separating another TargetElement)
     *  - Several keywords (e.g. `FROM`, `WHERE`...) that are all conveniently not bare-label
     */
    private function matchesTargetElementBound(Token $token): bool
    {
        return $token instanceof tokens\EOFToken
            || ($token instanceof tokens\KeywordToken && !$token->getKeyword()->isBareLabel())
            || $token->matches(TokenType::SPECIAL_CHAR, [',', ')']);
    }

    /**
     * Constructor, sets Lexer and Cache implementations to use
     *
     * It is recommended to always use cache in production: loading AST from cache is generally 3-4 times faster
     * than parsing.
     */
    public function __construct(private readonly Lexer $lexer, private ?CacheItemPoolInterface $cache = null)
    {
    }

    /**
     * Sets the cache object used for storing SQL parse results
     */
    public function setCache(CacheItemPoolInterface $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * Magic method for function overloading
     *
     * The method allows calling parseWhatever() methods that map to protected Whatever() methods
     * listed in $callable static property.
     *
     * @throws exceptions\BadMethodCallException
     * @throws exceptions\SyntaxException
     */
    public function __call(string $name, array $arguments): Node
    {
        if (
            !\preg_match('/^parse([a-zA-Z]+)$/', $name, $matches)
            || !isset(self::CALLABLE[\strtolower($matches[1])])
        ) {
            throw new exceptions\BadMethodCallException("The method '{$name}' is not available");
        }

        if (null !== $this->cache) {
            $source = $arguments[0] instanceof TokenStream ? $arguments[0]->getSource() : (string)$arguments[0];
            try {
                $cacheItem = $this->cache->getItem('parsetree-v3-' . \md5('{' . $name . '}' . $source));
                if ($cacheItem->isHit()) {
                    return clone $cacheItem->get();
                }
            } catch (InvalidArgumentException) {
            }
        }

        if ($arguments[0] instanceof TokenStream) {
            $this->stream = $arguments[0];
            $this->stream->reset();
        } else {
            $this->stream = $this->lexer->tokenize($arguments[0]);
        }

        $parsed = $this->{$matches[1]}();

        if (!$this->stream->isEOF()) {
            throw exceptions\SyntaxException::expectationFailed(
                TokenType::EOF,
                null,
                $this->stream->getCurrent(),
                $this->stream->getSource()
            );
        }

        if (null !== $this->cache && isset($cacheItem)) {
            $this->cache->save($cacheItem->set(clone $parsed));
        }

        return $parsed;
    }

    protected function Statement(): Statement
    {
        if (Keyword::WITH === $this->stream->getKeyword()) {
            $withClause = $this->WithClause();
        }

        if (
            $this->stream->matchesAnyKeyword(Keyword::SELECT, Keyword::VALUES)
            || $this->stream->matchesSpecialChar('(')
        ) {
            $stmt = $this->SelectStatement();
            if (!empty($withClause)) {
                if (0 < \count($stmt->with)) {
                    throw new exceptions\SyntaxException('Multiple WITH clauses are not allowed');
                }
                $stmt->with = $withClause;
            }
            return $stmt;

        } elseif (Keyword::INSERT === $this->stream->getKeyword()) {
            $stmt = $this->InsertStatement();

        } elseif (Keyword::UPDATE === $this->stream->getKeyword()) {
            $stmt = $this->UpdateStatement();

        } elseif (Keyword::DELETE === $this->stream->getKeyword()) {
            $stmt = $this->DeleteStatement();

        } elseif (Keyword::MERGE === $this->stream->getKeyword()) {
            $stmt = $this->MergeStatement();

        } else {
            throw new exceptions\SyntaxException(
                'Unexpected ' . $this->stream->getCurrent()->__toString()
                . ', expecting SELECT / INSERT / UPDATE / DELETE / MERGE statement'
            );
        }

        if (!empty($withClause)) {
            $stmt->with = $withClause;
        }
        return $stmt;
    }

    protected function SelectStatement(): SelectCommon
    {
        if (Keyword::WITH === $this->stream->getKeyword()) {
            $withClause = $this->WithClause();
        }

        $stmt = $this->SelectIntersect();

        while (null !== $base = $this->stream->matchesAnyKeyword(Keyword::UNION, Keyword::EXCEPT)) {
            $this->stream->next();
            $setOp = $base->value;
            if (null !== $mod = $this->stream->matchesAnyKeyword(Keyword::ALL, Keyword::DISTINCT)) {
                $this->stream->next();
                if (Keyword::ALL === $mod) {
                    $setOp .= ' all';
                }
            }
            $stmt = new SetOpSelect($stmt, $this->SelectIntersect(), enums\SetOperator::from($setOp));
        }

        if (!empty($withClause)) {
            if (0 < \count($stmt->with)) {
                throw new exceptions\SyntaxException(
                    'Multiple WITH clauses are not allowed'
                );
            }
            $stmt->with = $withClause;
        }

        // Per SQL spec ORDER BY and later clauses apply to a result of set operation,
        // not to a single participating SELECT
        if ($this->stream->matchesKeywordSequence(Keyword::ORDER, Keyword::BY)) {
            if (\count($stmt->order) > 0) {
                throw exceptions\SyntaxException::atPosition(
                    'Multiple ORDER BY clauses are not allowed',
                    $this->stream->getSource(),
                    $this->stream->getCurrent()->getPosition()
                );
            }
            $this->stream->skip(2);
            $stmt->order->replace($this->OrderByList());
        }

        // LIMIT / OFFSET clause and FOR [UPDATE] clause may come in any order
        if (
            null !== $keyword = $this->stream->matchesAnyKeyword(
                Keyword::FOR,
                Keyword::LIMIT,
                Keyword::OFFSET,
                Keyword::FETCH
            )
        ) {
            if (Keyword::FOR === $keyword) {
                // locking clause first
                $this->ForLockingClause($stmt);
                if ($this->stream->matchesAnyKeyword(Keyword::LIMIT, Keyword::OFFSET, Keyword::FETCH)) {
                    $this->LimitOffsetClause($stmt);
                }

            } else {
                // limit clause first
                $this->LimitOffsetClause($stmt);
                if (Keyword::FOR === $this->stream->getKeyword()) {
                    $this->ForLockingClause($stmt);
                }
            }
        }

        return $stmt;
    }

    protected function InsertStatement(): Insert
    {
        if (Keyword::WITH === $this->stream->getKeyword()) {
            $withClause = $this->WithClause();
        }
        $this->stream->expectKeyword(Keyword::INSERT);
        $this->stream->expectKeyword(Keyword::INTO);

        $stmt = new Insert($this->InsertTarget());
        if (!empty($withClause)) {
            $stmt->with = $withClause;
        }

        if ($this->stream->matchesKeywordSequence(Keyword::DEFAULT, Keyword::VALUES)) {
            $this->stream->skip(2);
        } else {
            if (
                $this->stream->matchesSpecialChar('(')
                && self::PARENTHESES_SELECT !== $this->checkContentsOfParentheses()
            ) {
                $this->stream->next();
                $stmt->cols->replace($this->InsertTargetList());
                $this->stream->expect(TokenType::SPECIAL_CHAR, ')');
            }
            if (Keyword::OVERRIDING === $this->stream->getKeyword()) {
                $this->stream->next();
                $stmt->setOverriding(enums\InsertOverriding::fromKeywords(
                    $this->stream->expectKeyword(Keyword::USER, Keyword::SYSTEM)
                ));
                $this->stream->expectKeyword(Keyword::VALUE);
            }
            $stmt->values = $this->SelectStatement();
        }

        if ($this->stream->matchesKeywordSequence(Keyword::ON, Keyword::CONFLICT)) {
            $this->stream->skip(2);
            $stmt->onConflict = $this->OnConflict();
        }

        if (Keyword::RETURNING === $this->stream->getKeyword()) {
            $this->stream->next();
            $stmt->returning->replace($this->TargetList());
        }

        return $stmt;
    }

    protected function UpdateStatement(): Update
    {
        if (Keyword::WITH === $this->stream->getKeyword()) {
            $withClause = $this->WithClause();
        }

        $this->stream->expectKeyword(Keyword::UPDATE);
        $relation = $this->UpdateOrDeleteTarget();
        $this->stream->expectKeyword(Keyword::SET);

        $stmt = new Update($relation, $this->SetClauseList());

        if (!empty($withClause)) {
            $stmt->with = $withClause;
        }

        if (Keyword::FROM === $this->stream->getKeyword()) {
            $this->stream->next();
            $stmt->from->replace($this->FromList());
        }
        if (Keyword::WHERE === $this->stream->getKeyword()) {
            $this->stream->next();
            if ($this->stream->matchesKeywordSequence(Keyword::CURRENT, Keyword::OF)) {
                throw new exceptions\NotImplementedException('WHERE CURRENT OF clause is not supported');
            }
            $stmt->where->condition = $this->Expression();
        }
        if (Keyword::RETURNING === $this->stream->getKeyword()) {
            $this->stream->next();
            $stmt->returning->replace($this->TargetList());
        }

        return $stmt;
    }

    protected function DeleteStatement(): Delete
    {
        if (Keyword::WITH === $this->stream->getKeyword()) {
            $withClause = $this->WithClause();
        }
        $this->stream->expectKeyword(Keyword::DELETE);
        $this->stream->expectKeyword(Keyword::FROM);

        $stmt = new Delete($this->UpdateOrDeleteTarget(self::RELATION_FORMAT_DELETE));

        if (!empty($withClause)) {
            $stmt->with = $withClause;
        }

        if (Keyword::USING === $this->stream->getKeyword()) {
            $this->stream->next();
            $stmt->using->replace($this->FromList());
        }
        if (Keyword::WHERE === $this->stream->getKeyword()) {
            $this->stream->next();
            if ($this->stream->matchesKeywordSequence(Keyword::CURRENT, Keyword::OF)) {
                throw new exceptions\NotImplementedException('WHERE CURRENT OF clause is not supported');
            }
            $stmt->where->condition = $this->Expression();
        }
        if (Keyword::RETURNING === $this->stream->getKeyword()) {
            $this->stream->next();
            $stmt->returning->replace($this->TargetList());
        }

        return $stmt;
    }

    protected function WithClause(): nodes\WithClause
    {
        $this->stream->expectKeyword(Keyword::WITH);
        if ($recursive = (Keyword::RECURSIVE === $this->stream->getKeyword())) {
            $this->stream->next();
        }

        $commonTableExpressions = [$this->CommonTableExpression()];
        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $commonTableExpressions[] = $this->CommonTableExpression();
        }

        return new nodes\WithClause($commonTableExpressions, $recursive);
    }

    protected function CommonTableExpression(): nodes\CommonTableExpression
    {
        $alias         = $this->ColId();
        $columnAliases = new nodes\lists\IdentifierList();
        $materialized  = null;
        $search        = null;
        $cycle         = null;
        if ($this->stream->matchesSpecialChar('(')) {
            do {
                $this->stream->next();
                $columnAliases[] = $this->ColId();
            } while ($this->stream->matchesSpecialChar(','));
            $this->stream->expect(TokenType::SPECIAL_CHAR, ')');
        }
        $this->stream->expectKeyword(Keyword::AS);

        if (Keyword::MATERIALIZED === $this->stream->getKeyword()) {
            $materialized = true;
            $this->stream->next();
        } elseif (Keyword::NOT === $this->stream->getKeyword()) {
            $materialized = false;
            $this->stream->next();
            $this->stream->expectKeyword(Keyword::MATERIALIZED);
        }

        $this->stream->expect(TokenType::SPECIAL_CHAR, '(');
        $statement = $this->Statement();
        $this->stream->expect(TokenType::SPECIAL_CHAR, ')');

        if (Keyword::SEARCH === $this->stream->getKeyword()) {
            $search = $this->SearchClause();
        }
        if (Keyword::CYCLE === $this->stream->getKeyword()) {
            $cycle = $this->CycleClause();
        }

        return new nodes\CommonTableExpression($statement, $alias, $columnAliases, $materialized, $search, $cycle);
    }

    protected function SearchClause(): nodes\cte\SearchClause
    {
        $this->stream->expectKeyword(Keyword::SEARCH);
        $first = $this->stream->expectKeyword(Keyword::BREADTH, Keyword::DEPTH);
        $this->stream->expectKeyword(Keyword::FIRST);

        $this->stream->expectKeyword(Keyword::BY);
        $trackColumns = $this->ColIdList();

        $this->stream->expectKeyword(Keyword::SET);
        $sequenceColumn = $this->ColId();

        return new nodes\cte\SearchClause(Keyword::BREADTH === $first, $trackColumns, $sequenceColumn);
    }

    protected function CycleClause(): nodes\cte\CycleClause
    {
        $this->stream->expectKeyword(Keyword::CYCLE);
        $trackColumns = $this->ColIdList();

        $this->stream->expectKeyword(Keyword::SET);
        $markColumn = $this->ColId();

        $markValue   = null;
        $markDefault = null;
        if (Keyword::TO === $this->stream->getKeyword()) {
            $this->stream->next();
            $markValue = $this->ConstantExpression();
            $this->stream->expectKeyword(Keyword::DEFAULT);
            $markDefault = $this->ConstantExpression();
        }

        $this->stream->expectKeyword(Keyword::USING);
        $pathColumn = $this->ColId();

        return new nodes\cte\CycleClause($trackColumns, $markColumn, $pathColumn, $markValue, $markDefault);
    }

    protected function ForLockingClause(SelectCommon $stmt): void
    {
        if ($this->stream->matchesKeywordSequence(Keyword::FOR, Keyword::READ, Keyword::ONLY)) {
            // this isn't quite documented but means "no locking" judging by the grammar
            $this->stream->skip(3);
            return;
        }

        if ($stmt instanceof Values) {
            throw exceptions\SyntaxException::atPosition(
                'SELECT FOR UPDATE/SHARE cannot be applied to VALUES',
                $this->stream->getSource(),
                $this->stream->getCurrent()->getPosition()
            );
        }

        // multiple locking clauses are allowed, so just append
        $stmt->locking->merge($this->LockingList());
    }

    protected function LockingList(): nodes\lists\LockList
    {
        $list = new nodes\lists\LockList();

        do {
            $list[] = $this->LockingElement();
        } while (Keyword::FOR === $this->stream->getKeyword());

        return $list;
    }

    protected function LockingElement(): nodes\LockingElement
    {
        $this->stream->expectKeyword(Keyword::FOR);
        $strength = [$this->stream->expectKeyword(Keyword::UPDATE, Keyword::NO, Keyword::SHARE, Keyword::KEY)];
        switch ($strength[0]) {
            case Keyword::NO:
                $strength[] = $this->stream->expectKeyword(Keyword::KEY);
                $strength[] = $this->stream->expectKeyword(Keyword::UPDATE);
                break;
            case Keyword::KEY:
                $strength[] = $this->stream->expectKeyword(Keyword::SHARE);
        }

        $relations  = [];
        $noWait     = false;
        $skipLocked = false;

        if (Keyword::OF === $this->stream->getKeyword()) {
            do {
                $this->stream->next();
                $relations[] = $this->QualifiedName();
            } while ($this->stream->matchesSpecialChar(','));
        }

        if (Keyword::NOWAIT === $this->stream->getKeyword()) {
            $this->stream->next();
            $noWait = true;

        } elseif ($this->stream->matchesKeywordSequence(Keyword::SKIP, Keyword::LOCKED)) {
            $this->stream->skip(2);
            $skipLocked = true;
        }

        return new nodes\LockingElement(
            enums\LockingStrength::fromKeywords(...$strength),
            $relations,
            $noWait,
            $skipLocked
        );
    }

    protected function LimitOffsetClause(SelectCommon $stmt): void
    {
        // LIMIT and OFFSET clauses may come in any order
        if (Keyword::OFFSET === $this->stream->getKeyword()) {
            $this->OffsetClause($stmt);
            if ($this->stream->matchesAnyKeyword(Keyword::LIMIT, Keyword::FETCH)) {
                $this->LimitClause($stmt);
            }
        } else {
            $this->LimitClause($stmt);
            if (Keyword::OFFSET === $this->stream->getKeyword()) {
                $this->OffsetClause($stmt);
            }
        }
    }

    protected function LimitClause(SelectCommon $stmt): void
    {
        if (null !== $stmt->limit) {
            throw exceptions\SyntaxException::atPosition(
                'Multiple LIMIT clauses are not allowed',
                $this->stream->getSource(),
                $this->stream->getCurrent()->getPosition()
            );
        }
        if (Keyword::LIMIT === $this->stream->getKeyword()) {
            // Traditional Postgres LIMIT clause
            $this->stream->next();
            if (Keyword::ALL === $this->stream->getKeyword()) {
                $this->stream->next();
                $stmt->limit = new nodes\expressions\KeywordConstant(enums\ConstantName::NULL);
            } else {
                $stmt->limit = $this->Expression();
            }

        } else {
            // SQL:2008 syntax
            $this->stream->expectKeyword(Keyword::FETCH);
            $this->stream->expectKeyword(Keyword::FIRST, Keyword::NEXT);

            if ($this->stream->matchesAnyKeyword(Keyword::ROW, Keyword::ROWS)) {
                // no limit specified -> 1 row
                $stmt->limit = new nodes\expressions\NumericConstant('1');
            } elseif ($this->stream->matchesSpecialChar(['+', '-'])) {
                // signed numeric constant: that case is not handled by ExpressionAtom()
                $sign = $this->stream->next();
                if ($this->stream->matches(TokenType::FLOAT)) {
                    $constantToken = $this->stream->next();
                } else {
                    $constantToken = $this->stream->expect(TokenType::INTEGER);
                }
                if ('+' === $sign->getValue()) {
                    $stmt->limit = nodes\expressions\Constant::createFromToken($constantToken);
                } else {
                    $stmt->limit = nodes\expressions\Constant::createFromToken(new tokens\StringToken(
                        $constantToken->getType(),
                        '-' . $constantToken->getValue(),
                        $constantToken->getPosition()
                    ));
                }
            } else {
                $stmt->limit = $this->ExpressionAtom();
            }

            $this->stream->expectKeyword(Keyword::ROW, Keyword::ROWS);
            if ($this->stream->matchesKeywordSequence(Keyword::WITH, Keyword::TIES)) {
                $stmt->limitWithTies = true;
                $this->stream->skip(2);
            } else {
                $this->stream->expectKeyword(Keyword::ONLY);
            }
        }
    }

    protected function OffsetClause(SelectCommon $stmt): void
    {
        if (null !== $stmt->offset) {
            throw exceptions\SyntaxException::atPosition(
                'Multiple OFFSET clauses are not allowed',
                $this->stream->getSource(),
                $this->stream->getCurrent()->getPosition()
            );
        }
        // NB: the following is a bit different from actual Postgres grammar, where offset only
        // allows c_expr (i.e. ExpressionAtom) production if trailed by ROW / ROWS, but full
        // a_expr (i.e. Expression) production in other case. We don't bother to do lookahead
        // here, so allow Expression in either case and treat trailing ROW / ROWS as noise
        $this->stream->expectKeyword(Keyword::OFFSET);
        $stmt->offset = $this->Expression();
        if ($this->stream->matchesAnyKeyword(Keyword::ROW, Keyword::ROWS)) {
            $this->stream->next();
        }
    }

    protected function SetClauseList(): nodes\lists\SetClauseList
    {
        $targetList = new nodes\lists\SetClauseList([$this->SetClause()]);
        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $targetList[] = $this->SetClause();
        }
        return $targetList;
    }

    protected function SetClause(): nodes\SingleSetClause|nodes\MultipleSetClause
    {
        if (!$this->stream->matchesSpecialChar('(')) {
            $column = $this->SetTargetElement();
            $this->stream->expect(TokenType::SPECIAL_CHAR, '=');
            $value  = $this->ExpressionWithDefault();

            return new nodes\SingleSetClause($column, $value);

        } else {
            $this->stream->next();

            $columns = new nodes\lists\SetTargetList([$this->SetTargetElement()]);
            while ($this->stream->matchesSpecialChar(',')) {
                $this->stream->next();
                $columns[] = $this->SetTargetElement();
            }
            $this->stream->expect(TokenType::SPECIAL_CHAR, ')');

            $this->stream->expect(TokenType::SPECIAL_CHAR, '=');

            $token = $this->stream->getCurrent();

            $value = $this->Expression();

            if (
                (!($value instanceof nodes\expressions\SubselectExpression) || $value->operator)
                && !($value instanceof nodes\expressions\RowExpression)
            ) {
                throw exceptions\SyntaxException::atPosition(
                    'source for a multiple-column UPDATE item must be a sub-SELECT or ROW() expression',
                    $this->stream->getSource(),
                    $token->getPosition()
                );
            }

            return new nodes\MultipleSetClause($columns, $value);
        }
    }

    protected function InsertTargetList(): nodes\lists\SetTargetList
    {
        $list = new nodes\lists\SetTargetList([$this->SetTargetElement()]);
        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $list[] = $this->SetTargetElement();
        }
        return $list;
    }

    protected function SetTargetElement(): nodes\SetTargetElement
    {
        $colId       = $this->ColId();
        // The whole point of parameter to Indirection() is to prevent Star nodes from appearing in array
        /** @var array<nodes\Identifier|nodes\ArrayIndexes> $indirection */
        $indirection = $this->Indirection(false);
        return new nodes\SetTargetElement($colId, $indirection);
    }

    /**
     * @return array<nodes\ScalarExpression|nodes\SetToDefault>
     */
    protected function ExpressionListWithDefault(): array
    {
        $values = [$this->ExpressionWithDefault()];
        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $values[] = $this->ExpressionWithDefault();
        }

        return $values;
    }

    protected function ExpressionWithDefault(): nodes\SetToDefault|nodes\ScalarExpression
    {
        if (Keyword::DEFAULT === $this->stream->getKeyword()) {
            $this->stream->next();
            return new nodes\SetToDefault();
        } else {
            return $this->Expression();
        }
    }

    protected function SelectWithParentheses(): SelectCommon
    {
        $this->stream->expect(TokenType::SPECIAL_CHAR, '(');
        $select = $this->SelectStatement();
        $this->stream->expect(TokenType::SPECIAL_CHAR, ')');

        return $select;
    }

    /**
     * SELECT ... [INTERSECT SELECT...]
     *
     */
    protected function SelectIntersect(): SelectCommon
    {
        $stmt = $this->SimpleSelect();

        while (Keyword::INTERSECT === $this->stream->getKeyword()) {
            $this->stream->next();
            $setOp = enums\SetOperator::INTERSECT;
            if (null !== $mod = $this->stream->matchesAnyKeyword(Keyword::ALL, Keyword::DISTINCT)) {
                $this->stream->next();
                if (Keyword::ALL === $mod) {
                    $setOp = enums\SetOperator::INTERSECT_ALL;
                }
            }
            $stmt = new SetOpSelect($stmt, $this->SimpleSelect(), $setOp);
        }

        return $stmt;
    }

    protected function SimpleSelect(): SelectCommon
    {
        if ($this->stream->matchesSpecialChar('(')) {
            return $this->SelectWithParentheses(); // select_with_parens grammar production
        }

        if (Keyword::VALUES === $this->stream->expectKeyword(Keyword::SELECT, Keyword::VALUES)) {
            return new Values($this->RowList());
        }

        $distinctClause = false;

        if (Keyword::ALL === $this->stream->getKeyword()) {
            // noise "ALL"
            $this->stream->next();
        } elseif (Keyword::DISTINCT === $this->stream->getKeyword()) {
            $this->stream->next();
            if (Keyword::ON !== $this->stream->getKeyword()) {
                $distinctClause = true;
            } else {
                $this->stream->next();
                $this->stream->expect(TokenType::SPECIAL_CHAR, '(');
                $distinctClause = $this->ExpressionList();
                $this->stream->expect(TokenType::SPECIAL_CHAR, ')');
            }
        }

        if (
            $distinctClause === false
            && (
                $this->stream->matchesAnyKeyword(
                    Keyword::INTO,
                    Keyword::FROM,
                    Keyword::WHERE,
                    Keyword::GROUP,
                    Keyword::HAVING,
                    Keyword::WINDOW,
                    Keyword::UNION,
                    Keyword::INTERSECT,
                    Keyword::EXCEPT,
                    Keyword::ORDER,
                    Keyword::LIMIT,
                    Keyword::OFFSET,
                    Keyword::FETCH,
                    Keyword::FOR
                )
                || $this->stream->matchesSpecialChar(')')
                || $this->stream->isEOF()
            )
        ) {
            $targetList = new nodes\lists\TargetList([]);
        } else {
            $targetList = $this->TargetList();
        }

        $stmt = new Select($targetList, $distinctClause);

        if (Keyword::INTO === $this->stream->getKeyword()) {
            throw new exceptions\NotImplementedException("SELECT INTO clauses are not supported");
        }

        if (Keyword::FROM === $this->stream->getKeyword()) {
            $this->stream->next();
            $stmt->from->replace($this->FromList());
        }

        if (Keyword::WHERE === $this->stream->getKeyword()) {
            $this->stream->next();
            $stmt->where->condition = $this->Expression();
        }

        if ($this->stream->matchesKeywordSequence(Keyword::GROUP, Keyword::BY)) {
            $this->stream->skip(2);
            $stmt->group->replace($this->GroupByClause());
        }

        if (Keyword::HAVING === $this->stream->getKeyword()) {
            $this->stream->next();
            $stmt->having->condition = $this->Expression();
        }

        if (Keyword::WINDOW === $this->stream->getKeyword()) {
            $this->stream->next();
            $stmt->window->replace($this->WindowList());
        }

        return $stmt;
    }

    protected function WindowList(): nodes\lists\WindowList
    {
        $windows = new nodes\lists\WindowList([$this->WindowDefinition()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $windows[] = $this->WindowDefinition();
        }

        return $windows;
    }

    protected function WindowDefinition(): nodes\WindowDefinition
    {
        $name    = $this->ColId();
        $this->stream->expectKeyword(Keyword::AS);
        $spec    = $this->WindowSpecification();
        $spec->setName($name);

        return $spec;
    }

    protected function WindowSpecification(): nodes\WindowDefinition
    {
        $refName = $partition = $frame = $order = null;
        $this->stream->expect(TokenType::SPECIAL_CHAR, '(');
        if (
            $this->stream->matchesAnyType(TokenType::IDENTIFIER, TokenType::COL_NAME_KEYWORD)
            || (
                $this->stream->matches(TokenType::UNRESERVED_KEYWORD)
                // See comment for opt_existing_window_name production in gram.y
                && !\in_array(
                    $this->stream->getKeyword(),
                    [Keyword::PARTITION, Keyword::RANGE, Keyword::ROWS, Keyword::GROUPS]
                )
            )
        ) {
            $refName = $this->ColId();
        }
        if ($this->stream->matchesKeywordSequence(Keyword::PARTITION, Keyword::BY)) {
            $this->stream->skip(2);
            $partition = $this->ExpressionList();
        }
        if ($this->stream->matchesKeywordSequence(Keyword::ORDER, Keyword::BY)) {
            $this->stream->skip(2);
            $order = $this->OrderByList();
        }
        if ($this->stream->matchesAnyKeyword(Keyword::RANGE, Keyword::ROWS, Keyword::GROUPS)) {
            $frame = $this->WindowFrameClause();
        }

        $this->stream->expect(TokenType::SPECIAL_CHAR, ')');

        return new nodes\WindowDefinition($refName, $partition, $order, $frame);
    }

    protected function WindowFrameClause(): nodes\WindowFrameClause
    {
        $mode       = $this->stream->expectKeyword(Keyword::RANGE, Keyword::ROWS, Keyword::GROUPS);
        $tokenStart = $this->stream->getCurrent();
        $exclusion  = null;
        if (Keyword::BETWEEN !== $tokenStart->getKeyword()) {
            $start = $this->WindowFrameBound();
            $end   = null;

        } else {
            $this->stream->next();
            $start = $this->WindowFrameBound();
            $this->stream->expectKeyword(Keyword::AND);
            $end   = $this->WindowFrameBound();
        }

        // opt_window_exclusion_clause from gram.y
        if (Keyword::EXCLUDE === $this->stream->getKeyword()) {
            $this->stream->next();
            $first = $this->stream->expectKeyword(Keyword::CURRENT, Keyword::GROUP, Keyword::TIES, Keyword::NO);
            switch ($first) {
                case Keyword::CURRENT:
                    $this->stream->expectKeyword(Keyword::ROW);
                    $exclusion = enums\WindowFrameExclusion::CURRENT_ROW;
                    break;
                case Keyword::NO:
                    $this->stream->expectKeyword(Keyword::OTHERS);
                    // EXCLUDE NO OTHERS is noise
                    break;
                default:
                    $exclusion = enums\WindowFrameExclusion::fromKeywords($first);
            }
        }

        // Repackage exceptions thrown in WindowFrameClause constructor as syntax ones and provide context
        try {
            return new nodes\WindowFrameClause(enums\WindowFrameMode::fromKeywords($mode), $start, $end, $exclusion);

        } catch (exceptions\InvalidArgumentException $e) {
            throw exceptions\SyntaxException::atPosition(
                $e->getMessage(),
                $this->stream->getSource(),
                $tokenStart->getPosition()
            );
        }
    }

    protected function WindowFrameBound(): nodes\WindowFrameBound
    {
        foreach (self::CHECKS_FRAME_BOUND as $check) {
            if ($this->stream->matchesKeywordSequence(...$check)) {
                $this->stream->skip(2);
                return new nodes\WindowFrameBound(enums\WindowFrameDirection::from(
                    Keyword::CURRENT === $check[0] ? 'current row' : $check[1]->value
                ));
            }
        }

        $value     = $this->Expression();
        $direction = $this->stream->expectKeyword(Keyword::PRECEDING, Keyword::FOLLOWING);
        return new nodes\WindowFrameBound(enums\WindowFrameDirection::fromKeywords($direction), $value);
    }

    protected function ExpressionList(): nodes\lists\ExpressionList
    {
        $expressions = new nodes\lists\ExpressionList([$this->Expression()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $expressions[] = $this->Expression();
        }

        return $expressions;
    }

    protected function Expression(bool $targetElement = false): nodes\ScalarExpression
    {
        $terms = [$this->LogicalExpressionTerm($targetElement)];

        while (Keyword::OR === $this->stream->getKeyword()) {
            if ($targetElement && $this->matchesTargetElementBound($this->stream->look())) {
                break;
            }
            $this->stream->next();

            $terms[] = $this->LogicalExpressionTerm($targetElement);
        }

        if (1 === \count($terms)) {
            return $terms[0];
        }

        return new nodes\expressions\LogicalExpression($terms, enums\LogicalOperator::OR);
    }

    protected function LogicalExpressionTerm(bool $targetElement): nodes\ScalarExpression
    {
        $factors = [$this->LogicalExpressionFactor($targetElement)];

        while (Keyword::AND === $this->stream->getKeyword()) {
            if ($targetElement && $this->matchesTargetElementBound($this->stream->look())) {
                break;
            }
            $this->stream->next();

            $factors[] = $this->LogicalExpressionFactor($targetElement);
        }

        if (1 === \count($factors)) {
            return $factors[0];
        }

        return new nodes\expressions\LogicalExpression($factors, enums\LogicalOperator::AND);
    }

    protected function LogicalExpressionFactor(bool $targetElement): nodes\ScalarExpression
    {
        if (Keyword::NOT === $this->stream->getKeyword()) {
            $this->stream->next();
            return new nodes\expressions\NotExpression($this->LogicalExpressionFactor($targetElement));
        }
        return $this->IsWhateverExpression(false, $targetElement);
    }

    /**
     * In Postgres 9.5+ all comparison operators have the same precedence and are non-associative
     */
    protected function Comparison(bool $restricted, bool $targetElement = false): nodes\ScalarExpression
    {
        $argument = $restricted
                    ? $this->GenericOperatorExpression(true)
                    : $this->PatternMatchingExpression($targetElement);

        if (
            $this->stream->matchesSpecialChar(['<', '>', '='])
            || $this->stream->matches(TokenType::INEQUALITY)
        ) {
            return new nodes\expressions\OperatorExpression(
                $this->stream->next()->getValue(),
                $argument,
                $restricted ? $this->GenericOperatorExpression(true) : $this->PatternMatchingExpression($targetElement)
            );
        }

        return $argument;
    }

    protected function PatternMatchingExpression(bool $targetElement): nodes\ScalarExpression
    {
        $string = $this->OverlapsExpression($targetElement);

        // speedup
        if (
            null === $keyword = $this->stream->matchesAnyKeyword(
                Keyword::LIKE,
                Keyword::ILIKE,
                Keyword::NOT,
                Keyword::SIMILAR
            )
        ) {
            return $string;
        }
        if (
            $targetElement && (Keyword::LIKE === $keyword || Keyword::ILIKE === $keyword)
            && $this->matchesTargetElementBound($this->stream->look())
        ) {
            return $string;
        }

        foreach (self::CHECKS_PATTERN_MATCHING as $checkIdx => $check) {
            if ($this->stream->matchesKeywordSequence(...$check)) {
                $this->stream->skip(\count($check));

                $escape = null;
                if ($checkIdx < 4 && $this->stream->matchesAnyKeyword(...self::SUBQUERY_EXPRESSIONS)) {
                    $pattern = $this->SubqueryExpression();

                } else {
                    $pattern = $this->OverlapsExpression($targetElement);
                    if (Keyword::ESCAPE === $this->stream->getKeyword()) {
                        $this->stream->next();
                        $escape = $this->OverlapsExpression($targetElement);
                    }
                }

                if (Keyword::NOT !== $check[0]) {
                    $negated = false;
                } else {
                    \array_shift($check);
                    $negated = true;
                }

                return new nodes\expressions\PatternMatchingExpression(
                    $string,
                    $pattern,
                    enums\PatternPredicate::fromKeywords(...$check),
                    $negated,
                    $escape
                );
            }
        }

        return $string;
    }

    protected function SubqueryExpression(): nodes\ScalarExpression
    {
        $type  = $this->stream->expectKeyword(...self::SUBQUERY_EXPRESSIONS);
        $check = $this->checkContentsOfParentheses();

        $this->stream->expect(TokenType::SPECIAL_CHAR, '(');
        if (self::PARENTHESES_SELECT === $check) {
            $result = new nodes\expressions\SubselectExpression(
                $this->SelectStatement(),
                enums\SubselectConstruct::fromKeywords($type)
            );
        } else {
            $result = new nodes\expressions\ArrayComparisonExpression(
                enums\ArrayComparisonConstruct::fromKeywords($type),
                $this->Expression()
            );
        }
        $this->stream->expect(TokenType::SPECIAL_CHAR, ')');

        return $result;
    }

    protected function OverlapsExpression(bool $targetElement): nodes\ScalarExpression
    {
        $left = $this->BetweenExpression($targetElement);

        if (
            !$left instanceof nodes\expressions\RowExpression
            || Keyword::OVERLAPS !== $this->stream->getKeyword()
        ) {
            return $left;
        }

        $token = $this->stream->next();
        $right = $this->RowConstructor();

        try {
            return new nodes\expressions\OverlapsExpression($left, $right);
        } catch (exceptions\InvalidArgumentException $e) {
            throw exceptions\SyntaxException::atPosition(
                $e->getMessage(),
                $this->stream->getSource(),
                $token->getPosition()
            );
        }
    }

    /**
     * Parses BETWEEN expressions
     *
     * Note that in pre-9.5 Postgres BETWEEN expressions were de-facto left-associative, so that
     * <code>
     * select 1 between 0 and 2 between false and true;
     * </code>
     * actually worked. Expressions of the form
     * <code>
     * select 2 between 3 and 4 is false;
     * </code>
     * resulted in syntax error but work in 9.5+
     */
    protected function BetweenExpression(bool $targetElement): nodes\ScalarExpression
    {
        $value = $this->InExpression($targetElement);

        if (null === $keyword = $this->stream->matchesAnyKeyword(Keyword::BETWEEN, Keyword::NOT)) {
            return $value;
        }

        if (Keyword::BETWEEN === $keyword) {
            if ($targetElement && $this->matchesTargetElementBound($this->stream->look())) {
                return $value;
            }
            $negated = false;
            $this->stream->next();
        } elseif (Keyword::BETWEEN !== $this->stream->look()->getKeyword()) {
            return $value;
        } else {
            $negated = true;
            $this->stream->skip(2);
        }
        $predicate = [Keyword::BETWEEN];
        if (null !== $keyword = $this->stream->matchesAnyKeyword(Keyword::SYMMETRIC, Keyword::ASYMMETRIC)) {
            $predicate[] = $keyword;
            $this->stream->next();
        }

        $left  = $this->GenericOperatorExpression(true);
        $this->stream->expectKeyword(Keyword::AND);
        // right argument of BETWEEN is defined as 'b_expr' in pre-9.5 grammar and as 'a_expr' afterwards
        $right = $this->GenericOperatorExpression(true, $targetElement);

        return new nodes\expressions\BetweenExpression(
            $value,
            $left,
            $right,
            enums\BetweenPredicate::fromKeywords(...$predicate),
            $negated
        );
    }

    protected function RestrictedExpression(): nodes\ScalarExpression
    {
        return $this->Comparison(true);
    }

    /**
     * Parses [NOT ] IN (...) expression
     *
     * The query
     * <code>
     * select 'foo' in ('foo', 'bar') in (true, false);
     * </code>
     * actually works, so we allow it here. That's pretty strange as [NOT ] IN is defined
     * %nonassoc in gram.y and the above code should be a syntax error per bison docs:
     * > %nonassoc specifies no associativity, which means that `x op y op z' is considered a syntax error.
     */
    protected function InExpression(bool $targetElement): nodes\ScalarExpression
    {
        $left = $this->GenericOperatorExpression(false, $targetElement);

        while (null !== $keyword = $this->stream->matchesAnyKeyword(Keyword::NOT, Keyword::IN)) {
            if (Keyword::IN === $keyword) {
                if ($targetElement && $this->matchesTargetElementBound($this->stream->look())) {
                    return $left;
                }
                $negated = false;
                $this->stream->next();
            } elseif (Keyword::IN !== $this->stream->look()->getKeyword()) {
                break;
            } else {
                $negated = true;
                $this->stream->skip(2);
            }

            $check = $this->checkContentsOfParentheses();
            $this->stream->expect(TokenType::SPECIAL_CHAR, '(');
            $right = self::PARENTHESES_SELECT === $check ? $this->SelectStatement() : $this->ExpressionList();
            $this->stream->expect(TokenType::SPECIAL_CHAR, ')');

            $left = new nodes\expressions\InExpression($left, $right, $negated);
        }

        return $left;
    }


    /**
     * Handles infix operators
     */
    protected function GenericOperatorExpression(bool $restricted, bool $targetElement = false): nodes\ScalarExpression
    {
        $leftOperand = $this->GenericOperatorTerm($restricted, $targetElement);

        while (
            ($op = $this->matchesOperator())
               || $this->stream->matches(TokenType::SPECIAL, self::MATH_OPERATORS)
                   && $this->stream->look(1)->matchesAnyKeyword(...self::SUBQUERY_EXPRESSIONS)
        ) {
            $operator = $op ? $this->Operator() : $this->stream->next()->getValue();
            if (!$op || $this->stream->matchesAnyKeyword(...self::SUBQUERY_EXPRESSIONS)) {
                // subquery operator
                $leftOperand = new nodes\expressions\OperatorExpression(
                    $operator,
                    $leftOperand,
                    $this->SubqueryExpression()
                );
            } else {
                $leftOperand = new nodes\expressions\OperatorExpression(
                    $operator,
                    $leftOperand,
                    $this->GenericOperatorTerm($restricted, $targetElement)
                );
            }
        }

        return $leftOperand;
    }

    protected function GenericOperatorTerm(bool $restricted, bool $targetElement): nodes\ScalarExpression
    {
        $operators = [];
        // prefix operator(s)
        while ($this->matchesOperator()) {
            $operators[] = $this->Operator();
        }
        $term = $this->ArithmeticExpression($restricted, $targetElement);
        // prefix operators are left-associative
        while (!empty($operators)) {
            $term = new nodes\expressions\OperatorExpression(\array_pop($operators), null, $term);
        }

        return $term;
    }

    /**
     * @param bool $all Whether to match qual_Op or qual_all_Op production
     *                  (the latter allows mathematical operators)
     */
    protected function Operator(bool $all = false): string|nodes\QualifiedOperator
    {
        if (
            $this->stream->matches(TokenType::OPERATOR)
            || $all && $this->stream->matches(TokenType::SPECIAL, self::MATH_OPERATORS)
        ) {
            return $this->stream->next()->getValue();
        }

        $this->stream->expectKeyword(Keyword::OPERATOR);
        $this->stream->expect(TokenType::SPECIAL_CHAR, '(');
        $parts = [];
        while (
            $this->stream->matchesAnyType(
                TokenType::IDENTIFIER,
                TokenType::UNRESERVED_KEYWORD,
                TokenType::COL_NAME_KEYWORD
            )
        ) {
            // ColId
            $parts[] = new nodes\Identifier($this->stream->next()->getValue());
            $this->stream->expect(TokenType::SPECIAL_CHAR, '.');
        }
        if ($this->stream->matches(TokenType::SPECIAL, self::MATH_OPERATORS)) {
            $parts[] = $this->stream->next()->getValue();
        } else {
            $parts[] = $this->stream->expect(TokenType::OPERATOR)->getValue();
        }
        $this->stream->expect(TokenType::SPECIAL_CHAR, ')');

        return new nodes\QualifiedOperator(...$parts);
    }

    protected function IsWhateverExpression(
        bool $restricted = false,
        bool $targetElement = false
    ): nodes\ScalarExpression {
        $operand = $this->Comparison($restricted, $targetElement);
        $isNot   = false;

        while (
            null !== $keyword = $restricted
                ? $this->stream->matchesAnyKeyword(Keyword::IS)
                : $this->stream->matchesAnyKeyword(Keyword::IS, Keyword::ISNULL, Keyword::NOTNULL)
        ) {
            if (
                $targetElement && Keyword::IS === $keyword
                && $this->matchesTargetElementBound($this->stream->look())
            ) {
                break;
            }

            $this->stream->next();
            if (Keyword::NOTNULL === $keyword) {
                $operand = new nodes\expressions\IsExpression($operand, enums\IsPredicate::NULL, true);
                continue;
            } elseif (Keyword::ISNULL === $keyword) {
                $operand = new nodes\expressions\IsExpression($operand, enums\IsPredicate::NULL);
                continue;
            }

            if (Keyword::NOT === $this->stream->getKeyword()) {
                $this->stream->next();
                $isNot = true;
            }

            foreach (
                \array_merge(
                    $restricted ? [] : self::CHECKS_IS_WHATEVER,
                    [[Keyword::DOCUMENT]]
                ) as $check
            ) {
                if ($this->stream->matchesKeywordSequence(...$check)) {
                    $isOperator = [];
                    for ($i = 0; $i < \count($check); $i++) {
                        $isOperator[] = $this->stream->next()->getValue();
                    }
                    if (['json'] !== $isOperator) {
                        $operand = new nodes\expressions\IsExpression(
                            $operand,
                            enums\IsPredicate::from(\implode(' ', $isOperator)),
                            $isNot
                        );
                    } else {
                        if (null !== $type = enums\IsJsonType::tryFrom($this->stream->getCurrent()->getValue())) {
                            $this->stream->next();
                        }
                        $operand = new nodes\expressions\IsJsonExpression(
                            $operand,
                            $isNot,
                            $type,
                            $this->JsonUniquenessConstraint()
                        );
                    }
                    continue 2;
                }
            }

            if ($this->stream->matchesKeywordSequence(Keyword::DISTINCT, Keyword::FROM)) {
                $this->stream->skip(2);
                return new nodes\expressions\IsDistinctFromExpression(
                    $operand,
                    $this->Comparison($restricted, $targetElement),
                    $isNot
                );
            }

            throw new exceptions\SyntaxException('Unexpected ' . $this->stream->getCurrent()->__toString());
        }

        return $operand;
    }

    protected function TypeName(): nodes\TypeName
    {
        $setOf  = false;
        $bounds = [];
        if (Keyword::SETOF === $this->stream->getKeyword()) {
            $this->stream->next();
            $setOf = true;
        }

        $typeName = $this->SimpleTypeName();
        $typeName->setSetOf($setOf);

        if (Keyword::ARRAY === $this->stream->getKeyword()) {
            $this->stream->next();
            if (!$this->stream->matchesSpecialChar('[')) {
                $bounds[] = -1;
            } else {
                $this->stream->next();
                $bounds[] = $this->stream->expect(TokenType::INTEGER)->getValue();
                $this->stream->expect(TokenType::SPECIAL_CHAR, ']');
            }

        } else {
            while ($this->stream->matchesSpecialChar('[')) {
                $this->stream->next();
                $bounds[] = $this->stream->matches(TokenType::INTEGER) ? $this->stream->next()->getValue() : -1;
                $this->stream->expect(TokenType::SPECIAL_CHAR, ']');
            }
        }
        $typeName->setBounds($bounds);

        return $typeName;
    }

    protected function SimpleTypeName(): nodes\TypeName
    {
        $typeName = $this->IntervalTypeName()
                    ?? $this->DateTimeTypeName()
                    ?? $this->CharacterTypeName()
                    ?? $this->BitTypeName()
                    ?? $this->NumericTypeName()
                    ?? $this->JsonTypeName()
                    ?? $this->GenericTypeName();

        if (null !== $typeName) {
            return $typeName;
        }

        throw exceptions\SyntaxException::atPosition(
            'Expecting type name',
            $this->stream->getSource(),
            $this->stream->getCurrent()->getPosition()
        );
    }

    protected function NumericTypeName(): ?nodes\TypeName
    {
        if (
            (null === $keyword = $this->stream->matchesAnyKeyword(...self::STANDARD_TYPES_NUMERIC))
            || (Keyword::DOUBLE === $keyword
                && Keyword::PRECISION !== $this->stream->look()->getKeyword())
        ) {
            return null;
        }

        $this->stream->next();
        $modifiers = null;
        if (Keyword::DOUBLE === $keyword) {
            $this->stream->next();
            $typeName = 'double precision';

        } elseif (Keyword::FLOAT === $keyword) {
            $floatName = 'float8';
            if ($this->stream->matchesSpecialChar('(')) {
                $this->stream->next();
                $precisionToken = $this->stream->expect(TokenType::INTEGER);
                $precision      = $precisionToken->getValue();
                if ($precision < 1) {
                    throw exceptions\SyntaxException::atPosition(
                        'Precision for type float must be at least 1 bit',
                        $this->stream->getSource(),
                        $precisionToken->getPosition()
                    );
                } elseif ($precision <= 24) {
                    $floatName = 'float4';
                } elseif ($precision >= 54) {
                    throw exceptions\SyntaxException::atPosition(
                        'Precision for type float must be less than 54 bits',
                        $this->stream->getSource(),
                        $precisionToken->getPosition()
                    );
                }
                $this->stream->expect(TokenType::SPECIAL_CHAR, ')');
            }
            return new nodes\TypeName(new nodes\QualifiedName('pg_catalog', $floatName));

        } elseif (Keyword::DECIMAL === $keyword || Keyword::DEC === $keyword || Keyword::NUMERIC === $keyword) {
            // NB: we explicitly require constants here, per comment in gram.y:
            // > To avoid parsing conflicts against function invocations, the modifiers
            // > have to be shown as expr_list here, but parse analysis will only accept
            // > constants for them.
            if ($this->stream->matchesSpecialChar('(')) {
                $this->stream->next();
                $modifiers = new nodes\lists\TypeModifierList([
                    nodes\expressions\Constant::createFromToken($this->stream->expect(TokenType::INTEGER))
                ]);
                if ($this->stream->matchesSpecialChar(',')) {
                    $this->stream->next();
                    $modifiers[] = nodes\expressions\Constant::createFromToken(
                        $this->stream->expect(TokenType::INTEGER)
                    );
                }
                $this->stream->expect(TokenType::SPECIAL_CHAR, ')');
            }
        }

        return new nodes\TypeName(
            new nodes\QualifiedName('pg_catalog', self::STANDARD_TYPES_MAPPING[$typeName ?? $keyword->value]),
            $modifiers
        );
    }

    protected function BitTypeName(bool $leading = false): ?nodes\TypeName
    {
        if (null === $keyword = $this->stream->matchesAnyKeyword(self::STANDARD_TYPES_BIT)) {
            return null;
        }

        $this->stream->next();
        $typeName  = $keyword->value;
        $modifiers = null;
        if (Keyword::VARYING === $this->stream->getKeyword()) {
            $this->stream->next();
            $typeName = 'varbit';
        }
        if ($this->stream->matchesSpecialChar('(')) {
            $this->stream->next();
            $modifiers = new nodes\lists\TypeModifierList([
                nodes\expressions\Constant::createFromToken($this->stream->expect(TokenType::INTEGER))
            ]);
            $this->stream->expect(TokenType::SPECIAL_CHAR, ')');
        }
        // BIT translates to bit(1) *unless* this is a leading typecast
        // where it translates to "any length" (with no modifiers)
        if (!$leading && $typeName === 'bit' && !$modifiers instanceof nodes\lists\TypeModifierList) {
            $modifiers = new nodes\lists\TypeModifierList([new nodes\expressions\NumericConstant('1')]);
        }
        return new nodes\TypeName(
            new nodes\QualifiedName('pg_catalog', $typeName),
            $modifiers
        );
    }

    protected function CharacterTypeName(bool $leading = false): ?nodes\TypeName
    {
        if (
            (null === $keyword = $this->stream->matchesAnyKeyword(...self::STANDARD_TYPES_CHARACTER))
            || (Keyword::NATIONAL === $keyword
                && !$this->stream->look()->matchesAnyKeyword(Keyword::CHARACTER, Keyword::CHAR))
        ) {
            return null;
        }

        $this->stream->next();
        $varying   = (Keyword::VARCHAR === $keyword);
        $modifiers = null;
        if (Keyword::NATIONAL === $keyword) {
            $this->stream->next();
        }
        if (!$varying && Keyword::VARYING === $this->stream->getKeyword()) {
            $this->stream->next();
            $varying = true;
        }
        if ($this->stream->matchesSpecialChar('(')) {
            $this->stream->next();
            $modifiers = new nodes\lists\TypeModifierList([
                nodes\expressions\Constant::createFromToken($this->stream->expect(TokenType::INTEGER))
            ]);
            $this->stream->expect(TokenType::SPECIAL_CHAR, ')');
        }
        // CHAR translates to char(1) *unless* this is a leading typecast
        // where it translates to "any length" (with no modifiers)
        if (!$leading && !$varying && null === $modifiers) {
            $modifiers = new nodes\lists\TypeModifierList([new nodes\expressions\NumericConstant('1')]);
        }

        return new nodes\TypeName(
            new nodes\QualifiedName('pg_catalog', $varying ? 'varchar' : 'bpchar'),
            $modifiers
        );
    }

    protected function DateTimeTypeName(): ?nodes\TypeName
    {
        if (null === $keyword = $this->stream->matchesAnyKeyword(...self::STANDARD_TYPES_DATETIME)) {
            return null;
        }

        $this->stream->next();
        $typeName  = $keyword->value;
        $modifiers = null;
        if ($this->stream->matchesSpecialChar('(')) {
            $this->stream->next();
            $modifiers = new nodes\lists\TypeModifierList([
                nodes\expressions\Constant::createFromToken($this->stream->expect(TokenType::INTEGER))
            ]);
            $this->stream->expect(TokenType::SPECIAL_CHAR, ')');
        }

        if ($this->stream->matchesKeywordSequence([Keyword::WITH, Keyword::WITHOUT], Keyword::TIME, Keyword::ZONE)) {
            if (Keyword::WITH === $this->stream->next()->getKeyword()) {
                $typeName .= 'tz';
            }
            $this->stream->skip(2);
        }

        return new nodes\TypeName(new nodes\QualifiedName('pg_catalog', $typeName), $modifiers);
    }

    protected function IntervalTypeName(): ?nodes\IntervalTypeName
    {
        if (Keyword::INTERVAL !== $this->stream->getKeyword()) {
            return null;
        }
        $this->stream->next();

        $modifiers = $this->IntervalTypeModifiers();

        return $this->IntervalWithPossibleTrailingTypeModifiers($modifiers);
    }

    protected function IntervalLeadingTypecast(): ?nodes\expressions\TypecastExpression
    {
        if (Keyword::INTERVAL !== $this->stream->getKeyword()) {
            return null;
        }
        $this->stream->next();

        $modifiers = $this->IntervalTypeModifiers();
        $operand   = nodes\expressions\Constant::createFromToken($this->stream->expect(TokenType::STRING));
        $typeNode  = $this->IntervalWithPossibleTrailingTypeModifiers($modifiers);

        return new nodes\expressions\TypecastExpression($operand, $typeNode);
    }

    protected function IntervalWithPossibleTrailingTypeModifiers(
        ?nodes\lists\TypeModifierList $modifiers = null
    ): nodes\IntervalTypeName {
        if (
            null === $modifiers
            && (null !== $keyword = $this->stream->matchesAnyKeyword(
                Keyword::YEAR,
                Keyword::MONTH,
                Keyword::DAY,
                Keyword::HOUR,
                Keyword::MINUTE,
                Keyword::SECOND
            ))
        ) {
            $this->stream->next();
            $trailing = [$keyword];
            $second   = Keyword::SECOND === $keyword;
            if (Keyword::TO === $this->stream->getKeyword()) {
                $toToken    = $this->stream->next();
                $trailing[] = Keyword::TO;
                $end        = match ($keyword) {
                    Keyword::YEAR => $this->stream->expectKeyword(Keyword::MONTH),
                    Keyword::DAY => $this->stream->expectKeyword(Keyword::HOUR, Keyword::MINUTE, Keyword::SECOND),
                    Keyword::HOUR => $this->stream->expectKeyword(Keyword::MINUTE, Keyword::SECOND),
                    Keyword::MINUTE => $this->stream->expectKeyword(Keyword::SECOND),
                    default => throw new exceptions\SyntaxException('Unexpected ' . $toToken->__toString())
                };
                $second     = Keyword::SECOND === $end;
                $trailing[] = $end;
            }

            if ($second) {
                $modifiers = $this->IntervalTypeModifiers();
            }
        }
        $typeNode = new nodes\IntervalTypeName($modifiers);
        if (!empty($trailing)) {
            $typeNode->setMask(enums\IntervalMask::fromKeywords(...$trailing));
        }

        return $typeNode;
    }

    protected function IntervalTypeModifiers(): ?nodes\lists\TypeModifierList
    {
        if (!$this->stream->matchesSpecialChar('(')) {
            $modifiers = null;
        } else {
            $this->stream->next();
            $modifiers = new nodes\lists\TypeModifierList([
                nodes\expressions\Constant::createFromToken($this->stream->expect(TokenType::INTEGER))
            ]);
            $this->stream->expect(TokenType::SPECIAL_CHAR, ')');
        }
        return $modifiers;
    }

    protected function JsonTypeName(): ?nodes\TypeName
    {
        if (Keyword::JSON !== $this->stream->getKeyword()) {
            return null;
        }
        $this->stream->next();
        return new nodes\TypeName(new nodes\QualifiedName('pg_catalog', 'json'));
    }

    protected function GenericTypeName(): ?nodes\TypeName
    {
        if (
            !$this->stream->matchesAnyType(
                TokenType::IDENTIFIER,
                TokenType::UNRESERVED_KEYWORD,
                TokenType::TYPE_FUNC_NAME_KEYWORD
            )
        ) {
            return null;
        }

        $typeName = [new nodes\Identifier($this->stream->next()->getValue())];
        while ($this->stream->matchesSpecialChar('.')) {
            $this->stream->next();
            $typeName[] = $this->ColLabel();
        }

        return new nodes\TypeName(new nodes\QualifiedName(...$typeName), $this->GenericTypeModifierList());
    }

    protected function GenericTypeModifierList(): ?nodes\lists\TypeModifierList
    {
        if (!$this->stream->matchesSpecialChar('(')) {
            return null;
        }

        $this->stream->next();
        $modifiers = new nodes\lists\TypeModifierList([$this->GenericTypeModifier()]);
        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $modifiers[] = $this->GenericTypeModifier();
        }
        $this->stream->expect(TokenType::SPECIAL_CHAR, ')');
        return $modifiers;
    }

    /**
     * Gets a type modifier for a "generic" type
     *
     * Type modifiers here are allowed according to typenameTypeMod() function from
     * src/backend/parser/parse_type.c
     */
    protected function GenericTypeModifier(): nodes\expressions\Constant|nodes\Identifier
    {
        // Let's keep most common case at the top
        if ($this->stream->matchesAnyType(TokenType::INTEGER, TokenType::FLOAT, TokenType::STRING)) {
            return nodes\expressions\Constant::createFromToken($this->stream->next());

        } elseif (
            $this->stream->matchesAnyType(
                TokenType::IDENTIFIER,
                TokenType::UNRESERVED_KEYWORD,
                TokenType::TYPE_FUNC_NAME_KEYWORD
            )
        ) {
            // allows ColId
            return new nodes\Identifier($this->stream->next()->getValue());

        } else {
            throw new exceptions\SyntaxException(
                "Expecting a constant or an identifier, got " . $this->stream->getCurrent()->__toString()
            );
        }
    }

    protected function ArithmeticExpression(bool $restricted, bool $targetElement): nodes\ScalarExpression
    {
        $leftOperand = $this->ArithmeticTerm($restricted, $targetElement);

        while ($this->stream->matchesSpecialChar(['+', '-'])) {
            $operator = $this->stream->next()->getValue();
            $leftOperand = new nodes\expressions\OperatorExpression(
                $operator,
                $leftOperand,
                $this->ArithmeticTerm($restricted, $targetElement)
            );
        }

        return $leftOperand;
    }

    protected function ArithmeticTerm(bool $restricted, bool $targetElement): nodes\ScalarExpression
    {
        $leftOperand = $this->ArithmeticMultiplier($restricted, $targetElement);

        while ($this->stream->matchesSpecialChar(['*', '/', '%'])) {
            $operator = $this->stream->next()->getValue();
            $leftOperand = new nodes\expressions\OperatorExpression(
                $operator,
                $leftOperand,
                $this->ArithmeticMultiplier($restricted, $targetElement)
            );
        }

        return $leftOperand;
    }

    protected function ArithmeticMultiplier(bool $restricted, bool $targetElement): nodes\ScalarExpression
    {
        $leftOperand = $restricted
                       ? $this->UnaryPlusMinusExpression()
                       : $this->AtTimeZoneExpression($targetElement);

        while ($this->stream->matchesSpecialChar('^')) {
            $operator    = $this->stream->next()->getValue();
            $leftOperand = new nodes\expressions\OperatorExpression(
                $operator,
                $leftOperand,
                $restricted ? $this->UnaryPlusMinusExpression() : $this->AtTimeZoneExpression($targetElement)
            );
        }

        return $leftOperand;
    }

    protected function AtTimeZoneExpression(bool $targetElement): nodes\ScalarExpression
    {
        $left = $this->CollateExpression($targetElement);

        if (Keyword::AT === $this->stream->getKeyword()) {
            if (Keyword::LOCAL === $this->stream->look()->getKeyword()) {
                $this->stream->skip(2);
                return new nodes\expressions\AtLocalExpression($left);
            } elseif (
                Keyword::TIME === $this->stream->look()->getKeyword()
                && Keyword::ZONE === $this->stream->look(2)->getKeyword()
            ) {
                $this->stream->skip(3);
                return new nodes\expressions\AtTimeZoneExpression($left, $this->CollateExpression($targetElement));
            }
        }

        return $left;
    }

    protected function CollateExpression(bool $targetElement): nodes\ScalarExpression
    {
        $left = $this->UnaryPlusMinusExpression();
        if (
            Keyword::COLLATE === $this->stream->getKeyword()
            && !($targetElement && $this->matchesTargetElementBound($this->stream->look()))
        ) {
            $this->stream->next();
            return new nodes\expressions\CollateExpression($left, $this->QualifiedName());
        }
        return $left;
    }

    protected function UnaryPlusMinusExpression(): nodes\ScalarExpression
    {
        if (!$this->stream->matchesSpecialChar(['+', '-'])) {
            return $this->TypecastExpression();
        }

        $token    = $this->stream->next();
        $operator = $token->getValue();
        $operand  = $this->UnaryPlusMinusExpression();
        if (!$operand instanceof nodes\expressions\NumericConstant || '-' !== $operator) {
            return new nodes\expressions\OperatorExpression($operator, null, $operand);
        } elseif ('-' === $operand->value[0]) {
            return new nodes\expressions\NumericConstant(\substr($operand->value, 1));
        } else {
            return new nodes\expressions\NumericConstant('-' . $operand->value);
        }
    }

    protected function TypecastExpression(): nodes\ScalarExpression
    {
        $left = $this->ExpressionAtom();

        while ($this->stream->matches(TokenType::TYPECAST)) {
            $this->stream->next();
            $left = new nodes\expressions\TypecastExpression($left, $this->TypeName());
        }

        return $left;
    }

    protected function ExpressionAtom(): nodes\ScalarExpression
    {
        switch ($keyword = $this->stream->matchesAnyKeyword(...self::ATOM_KEYWORDS)) {
            case null:
                $token     = $this->stream->getCurrent();
                $tokenType = $token->getType();
                if (0 === ($tokenType->value & self::ATOM_SPECIAL_TYPES)) {
                    break;
                }
                switch ($tokenType) {
                    case TokenType::SPECIAL_CHAR:
                        if ('(' !== $token->getValue()) {
                            break;
                        }
                        switch ($this->checkContentsOfParentheses()) {
                            case self::PARENTHESES_ROW:
                                return $this->RowConstructor();

                            case self::PARENTHESES_SELECT:
                                $atom = new nodes\expressions\SubselectExpression($this->SelectWithParentheses());
                                break;

                            case self::PARENTHESES_EXPRESSION:
                            default:
                                $this->stream->next();
                                $atom = $this->Expression();
                                $this->stream->expect(TokenType::SPECIAL_CHAR, ')');
                        }
                        break;

                    case TokenType::POSITIONAL_PARAM:
                    case TokenType::NAMED_PARAM:
                        $atom = nodes\expressions\Parameter::createFromToken($this->stream->next());
                        break;

                    default:
                        if ($token->matches(TokenType::LITERAL)) {
                            return nodes\expressions\Constant::createFromToken($this->stream->next());
                        }
                }
                break;

            case Keyword::ROW:
                if ($this->stream->look()->matches(TokenType::SPECIAL_CHAR, '(')) {
                    return $this->RowConstructor();
                }
                break;

            case Keyword::ARRAY:
                return $this->ArrayConstructor();

            case Keyword::EXISTS:
                $this->stream->next();
                return new nodes\expressions\SubselectExpression(
                    $this->SelectWithParentheses(),
                    enums\SubselectConstruct::EXISTS
                );

            case Keyword::CASE:
                return $this->CaseExpression();

            case Keyword::GROUPING:
                return $this->GroupingExpression();

            default:
                $this->stream->next();
                return new nodes\expressions\KeywordConstant(enums\ConstantName::fromKeywords($keyword));
        }

        if (!isset($atom)) {
            if (null !== $this->stream->getKeyword()) {
                if ($this->matchesConstTypecast()) {
                    return $this->ConstLeadingTypecast();
                } elseif (
                    $this->matchesSpecialFunctionCall()
                    && null !== ($function = $this->SpecialFunctionCall() ?? $this->JsonAggregateFunc())
                ) {
                    return $this->convertSpecialFunctionCallToFunctionExpression($function);
                }
            }

            // By the time we got here everything that can still legitimately be matched should
            // start with a (potentially qualified) name. To prevent back-and-forth match()ing and look()ing
            // the NamedExpressionAtom() matches such name as far as possible
            // and then passes the matched parts for further processing if expression looks legit.
            return $this->NamedExpressionAtom();
        }

        if ([] !== ($indirection = $this->Indirection())) {
            return new nodes\Indirection($indirection, $atom);
        }

        return $atom;
    }

    /**
     * Represents AexprConst production from the grammar, used only in CYCLE clause currently
     */
    protected function ConstantExpression(): nodes\expressions\Constant|nodes\expressions\ConstantTypecastExpression
    {
        if (
            $this->stream->matchesAnyKeyword(Keyword::NULL, Keyword::TRUE, Keyword::FALSE)
            || $this->stream->matches(TokenType::LITERAL)
        ) {
            return nodes\expressions\Constant::createFromToken($this->stream->next());
        }
        if ($this->matchesConstTypecast()) {
            $typecast = $this->ConstLeadingTypecast();
        } else {
            // We are only interested in typecasts here, everything else is an error according to AexprConst
            $token    = $this->stream->getCurrent();
            $typecast = $this->NamedExpressionAtom();
            if (!$typecast instanceof nodes\expressions\TypecastExpression) {
                throw exceptions\SyntaxException::atPosition(
                    "Unexpected {$token}, expecting constant expression",
                    $this->stream->getSource(),
                    $token->getPosition()
                );
            }
        }
        return new nodes\expressions\ConstantTypecastExpression(clone $typecast->argument, clone $typecast->type);
    }

    protected function NamedExpressionAtom(): nodes\ScalarExpression
    {
        $token       = $this->stream->getCurrent();
        $firstType   = $token->getType();

        // This will throw an Exception if current token in stream cannot start a name
        if (!\in_array($firstType, self::ATOM_IDENTIFIER_TYPES)) {
            $this->stream->expect(TokenType::IDENTIFIER);
        }

        $identifiers = [new nodes\Identifier($token->getValue())];

        $lookIdx = 1;
        while ($this->stream->look($lookIdx)->matches(TokenType::SPECIAL_CHAR, '.')) {
            $token = $this->stream->look($lookIdx + 1);
            if (!$token->matches(TokenType::IDENTIFIER) && !$token->matches(TokenType::KEYWORD)) {
                break;
            }
            $identifiers[]  = new nodes\Identifier($token->getValue());
            $lookIdx       += 2;
        }

        if (
            // check that whatever we got looks like func_name production
            1 === \count($identifiers)
                ? TokenType::COL_NAME_KEYWORD !== $firstType
                : TokenType::TYPE_FUNC_NAME_KEYWORD !== $firstType
        ) {
            if ($this->stream->look($lookIdx)->matches(TokenType::STRING)) {
                $this->stream->skip($lookIdx);
                return $this->GenericLeadingTypecast($identifiers);
            } elseif ($this->stream->look($lookIdx)->matches(TokenType::SPECIAL_CHAR, '(')) {
                $this->stream->skip($lookIdx);
                if ($this->stream->look(1)->matches(TokenType::SPECIAL_CHAR, ')')) {
                    return $this->FunctionExpression($identifiers);
                } else {
                    $beyond = $this->skipParentheses(0);
                    return $this->stream->look($beyond)->matches(TokenType::STRING)
                           ? $this->GenericLeadingTypecast($identifiers)
                           : $this->FunctionExpression($identifiers);
                }
            }
        }

        // This will throw an exception if matched name is an invalid ColumnReference
        if (TokenType::TYPE_FUNC_NAME_KEYWORD === $firstType) {
            $this->stream->expect(TokenType::IDENTIFIER);
        }

        $this->stream->skip($lookIdx);
        $indirection = $this->Indirection();
        while ([] !== $indirection && !($indirection[0] instanceof nodes\ArrayIndexes)) {
            $identifiers[] = \array_shift($indirection);
        }
        /** @var array<nodes\Identifier|nodes\Star> $identifiers */
        if ([] !== $indirection) {
            return new nodes\Indirection($indirection, new nodes\ColumnReference(...$identifiers));
        }

        return new nodes\ColumnReference(...$identifiers);
    }

    protected function RowList(): nodes\lists\RowList
    {
        $list = new nodes\lists\RowList([$this->RowConstructorNoKeyword()]);
        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $list[] = $this->RowConstructorNoKeyword();
        }
        return $list;
    }

    protected function RowConstructor(): nodes\expressions\RowExpression
    {
        if (Keyword::ROW === $this->stream->getKeyword()) {
            $this->stream->next();
        }
        // ROW() is only possible with the keyword, 'VALUES ()' is a syntax error
        if (
            $this->stream->matchesSpecialChar('(')
            && $this->stream->look()->matches(TokenType::SPECIAL_CHAR, ')')
        ) {
            $this->stream->skip(2);
            return new nodes\expressions\RowExpression([]);
        }

        return $this->RowConstructorNoKeyword();
    }

    protected function RowConstructorNoKeyword(): nodes\expressions\RowExpression
    {
        $this->stream->expect(TokenType::SPECIAL_CHAR, '(');
        $fields = $this->ExpressionListWithDefault();
        $this->stream->expect(TokenType::SPECIAL_CHAR, ')');

        return new nodes\expressions\RowExpression($fields);
    }

    protected function ArrayConstructor(): nodes\expressions\SubselectExpression|nodes\expressions\ArrayExpression
    {
        $this->stream->expectKeyword(Keyword::ARRAY);
        if (!$this->stream->matchesSpecialChar(['[', '('])) {
            throw exceptions\SyntaxException::expectationFailed(
                TokenType::SPECIAL_CHAR,
                ['[', '('],
                $this->stream->getCurrent(),
                $this->stream->getSource()
            );

        } elseif ('(' === $this->stream->getCurrent()->getValue()) {
            return new nodes\expressions\SubselectExpression(
                $this->SelectWithParentheses(),
                enums\SubselectConstruct::ARRAY
            );

        } else {
            return $this->ArrayExpression();
        }
    }

    protected function ArrayExpression(): nodes\expressions\ArrayExpression
    {
        $this->stream->expect(TokenType::SPECIAL_CHAR, '[');
        if ($this->stream->matchesSpecialChar(']')) {
            $this->stream->next();
            return new nodes\expressions\ArrayExpression();
        }

        if (!$this->stream->matchesSpecialChar('[')) {
            $array = new nodes\expressions\ArrayExpression($this->ExpressionList());

        } else {
            $array = new nodes\expressions\ArrayExpression([$this->ArrayExpression()]);
            while ($this->stream->matchesSpecialChar(',')) {
                $this->stream->next();
                $array[] = $this->ArrayExpression();
            }
        }
        $this->stream->expect(TokenType::SPECIAL_CHAR, ']');

        return $array;
    }

    protected function CaseExpression(): nodes\expressions\CaseExpression
    {
        $argument    = null;
        $whenClauses = [];
        $elseClause  = null;

        $this->stream->expectKeyword(Keyword::CASE);
        // "simple" variant?
        if (Keyword::WHEN !== $this->stream->getKeyword()) {
            $argument = $this->Expression();
        }

        // requires at least one WHEN clause
        do {
            $this->stream->expectKeyword(Keyword::WHEN);
            $when = $this->Expression();
            $this->stream->expectKeyword(Keyword::THEN);
            $then = $this->Expression();
            $whenClauses[] = new nodes\expressions\WhenExpression($when, $then);
        } while (Keyword::WHEN === $this->stream->getKeyword());

        // may have an ELSE clause
        if (Keyword::ELSE === $this->stream->getKeyword()) {
            $this->stream->next();
            $elseClause = $this->Expression();
        }
        $this->stream->expectKeyword(Keyword::END);

        return new nodes\expressions\CaseExpression($whenClauses, $elseClause, $argument);
    }

    protected function GroupingExpression(): nodes\expressions\GroupingExpression
    {
        $this->stream->expectKeyword(Keyword::GROUPING);
        $this->stream->expect(TokenType::SPECIAL_CHAR, '(');
        $expression = new nodes\expressions\GroupingExpression($this->ExpressionList());
        $this->stream->expect(TokenType::SPECIAL_CHAR, ')');

        return $expression;
    }

    protected function ConstLeadingTypecast(): nodes\expressions\TypecastExpression
    {
        if (null !== ($typeCast = $this->IntervalLeadingTypecast())) {
            // interval is a special case since its options may come *after* string constant
            return $typeCast;
        }

        $typeName = $this->DateTimeTypeName()
                    ?? $this->CharacterTypeName(true)
                    ?? $this->BitTypeName(true)
                    ?? $this->NumericTypeName()
                    ?? $this->JsonTypeName();

        if (null !== $typeName) {
            return new nodes\expressions\TypecastExpression(
                nodes\expressions\Constant::createFromToken($this->stream->expect(TokenType::STRING)),
                $typeName
            );
        }

        throw new exceptions\SyntaxException(
            'Expecting type name, got ' . $this->stream->getCurrent()->__toString()
        );
    }

    protected function GenericLeadingTypecast(array $identifiers): nodes\expressions\TypecastExpression
    {
        $modifiers = $this->GenericTypeModifierList();
        return new nodes\expressions\TypecastExpression(
            nodes\expressions\Constant::createFromToken($this->stream->expect(TokenType::STRING)),
            new nodes\TypeName(new nodes\QualifiedName(...$identifiers), $modifiers)
        );
    }

    protected function SQLValueFunction(): ?nodes\expressions\SQLValueFunction
    {
        if (
            (null === $keyword = $this->stream->getKeyword())
            || (null === $funcName = enums\SQLValueFunctionName::tryFromKeywords($keyword))
        ) {
            return null;
        }

        $this->stream->next();
        $modifier = null;
        if ($funcName->allowsModifiers() && $this->stream->matchesSpecialChar('(')) {
            $this->stream->next();
            $modifier = new nodes\expressions\NumericConstant(
                $this->stream->expect(TokenType::INTEGER)->getValue()
            );
            $this->stream->expect(TokenType::SPECIAL_CHAR, ')');
        }

        return new nodes\expressions\SQLValueFunction($funcName, $modifier);
    }

    protected function SystemFunctionCall(): nodes\FunctionLike|nodes\ScalarExpression|null
    {
        if (null === $funcName = $this->stream->matchesAnyKeyword(...self::SYSTEM_FUNCTIONS)) {
            return null;
        }
        $this->stream->next();
        $this->stream->expect(TokenType::SPECIAL_CHAR, '(');

        switch ($funcName) {
            case Keyword::TREAT:
                // TREAT is "a bit" undocumented and buggy:
                // select treat('' as public.hstore); -> ERROR: function pg_catalog.hstore(unknown) does not exist
                // can be traced to revision 68d9fbeb5511d846ce3a6f66b8955d3ca55a4b76 from 2002
                throw new exceptions\NotImplementedException('TREAT() function support is not implemented');

            case Keyword::CAST:
                $value = $this->Expression();
                $this->stream->expectKeyword(Keyword::AS);
                $funcNode = new nodes\expressions\TypecastExpression($value, $this->TypeName());
                break;

            case Keyword::EXTRACT:
                $token = $this->stream->getCurrent();
                if (
                    $token instanceof tokens\KeywordToken
                    && (null !== $field = enums\ExtractPart::tryFromKeywords($token->getKeyword()))
                ) {
                    $this->stream->next();
                } elseif ($token->matches(TokenType::STRING)) {
                    $field = $this->stream->next()->getValue();
                } else {
                    $field = $this->stream->expect(TokenType::IDENTIFIER)->getValue();
                }

                $this->stream->expectKeyword(Keyword::FROM);
                $funcNode = new nodes\expressions\ExtractExpression($field, $this->Expression());
                break;

            case Keyword::OVERLAY:
                $funcNode = $this->OverlayExpressionFromArguments();
                break;

            case Keyword::POSITION:
                $substring = $this->RestrictedExpression();
                $this->stream->expectKeyword(Keyword::IN);
                $funcNode = new nodes\expressions\PositionExpression($substring, $this->RestrictedExpression());
                break;

            case Keyword::SUBSTRING:
                $funcNode = $this->SubstringExpressionFromArguments();
                break;

            case Keyword::TRIM:
                $funcNode = new nodes\expressions\TrimExpression(...$this->TrimFunctionArguments());
                break;

            case Keyword::NULLIF: // only two arguments, so don't use ExpressionList()
                $first    = $this->Expression();
                $this->stream->expect(TokenType::SPECIAL_CHAR, ',');
                $second   = $this->Expression();
                $funcNode = new nodes\expressions\NullIfExpression($first, $second);
                break;

            case Keyword::XMLELEMENT:
                $funcNode = $this->XmlElementFunction();
                break;

            case Keyword::XMLEXISTS:
                $funcNode = new nodes\xml\XmlExists(...$this->XmlExistsArguments());
                break;

            case Keyword::XMLFOREST:
                $funcNode = new nodes\xml\XmlForest($this->XmlAttributeList());
                break;

            case Keyword::XMLPARSE:
                $docOrContent = enums\XmlOption::fromKeywords(
                    $this->stream->expectKeyword(Keyword::DOCUMENT, Keyword::CONTENT)
                );
                $value        = $this->Expression();
                $preserve     = false;
                if ($this->stream->matchesKeywordSequence(Keyword::PRESERVE, Keyword::WHITESPACE)) {
                    $preserve = true;
                    $this->stream->skip(2);
                } elseif ($this->stream->matchesKeywordSequence(Keyword::STRIP, Keyword::WHITESPACE)) {
                    $this->stream->skip(2);
                }
                $funcNode = new nodes\xml\XmlParse($docOrContent, $value, $preserve);
                break;

            case Keyword::XMLPI:
                $this->stream->expectKeyword(Keyword::NAME);
                $name    = $this->ColLabel();
                $content = null;
                if ($this->stream->matchesSpecialChar(',')) {
                    $this->stream->next();
                    $content = $this->Expression();
                }

                $funcNode = new nodes\xml\XmlPi($name, $content);
                break;

            case Keyword::XMLROOT:
                $funcNode = $this->XmlRoot();
                break;

            case Keyword::XMLSERIALIZE:
                $docOrContent = enums\XmlOption::fromKeywords(
                    $this->stream->expectKeyword(Keyword::DOCUMENT, Keyword::CONTENT)
                );
                $value        = $this->Expression();
                $this->stream->expectKeyword(Keyword::AS);
                $typeName     = $this->SimpleTypeName();
                $indent       = null;
                if (null !== $keyword = $this->stream->matchesAnyKeyword(Keyword::NO, Keyword::INDENT)) {
                    $indent = Keyword::INDENT === $keyword;
                    $this->stream->next();
                    if (!$indent) {
                        $this->stream->expectKeyword(Keyword::INDENT);
                    }
                }
                $funcNode     = new nodes\xml\XmlSerialize($docOrContent, $value, $typeName, $indent);
                break;

            case Keyword::NORMALIZE:
                $argument = $this->Expression();
                if ($this->stream->matchesSpecialChar(',')) {
                    $this->stream->skip(1);
                    $form = $this->stream->expectKeyword(...enums\NormalizeForm::toKeywords());
                }
                $funcNode = new nodes\expressions\NormalizeExpression(
                    $argument,
                    empty($form) ? null : enums\NormalizeForm::fromKeywords($form),
                );
                break;

            case Keyword::JSON_OBJECT:
                $funcNode = $this->JsonObjectConstructor();
                break;

            case Keyword::JSON_ARRAY:
                $funcNode = $this->JsonArrayConstructor();
                break;

            case Keyword::JSON:
                $funcNode = new nodes\json\JsonConstructor(
                    $this->JsonFormattedValue(),
                    $this->JsonUniquenessConstraint()
                );
                break;

            case Keyword::JSON_SCALAR:
                $funcNode = new nodes\json\JsonScalar($this->Expression());
                break;

            case Keyword::JSON_SERIALIZE:
                $funcNode = new nodes\json\JsonSerialize($this->JsonFormattedValue(), $this->JsonReturningClause());
                break;

            case Keyword::JSON_EXISTS:
            case Keyword::JSON_VALUE:
            case Keyword::JSON_QUERY:
                $funcNode = $this->JsonQueryFunction($funcName->value);
                break;

            case Keyword::MERGE_ACTION:
                $funcNode = new nodes\expressions\MergeAction();
                break;

            default: // 'coalesce', 'greatest', 'least', 'xmlconcat'
                $funcNode = new nodes\expressions\SystemFunctionCall(
                    enums\SystemFunctionName::fromKeywords($funcName),
                    $this->ExpressionList()
                );
        }

        $this->stream->expect(TokenType::SPECIAL_CHAR, ')');
        return $funcNode;
    }

    /**
     * @return array{nodes\lists\ExpressionList, enums\TrimSide}
     */
    protected function TrimFunctionArguments(): array
    {
        if (null !== $keyword = $this->stream->matchesAnyKeyword(...enums\TrimSide::toKeywords())) {
            $this->stream->next();
            $side = enums\TrimSide::fromKeywords($keyword);
        } else {
            $side = enums\TrimSide::BOTH;
        }

        if (Keyword::FROM === $this->stream->getKeyword()) {
            $this->stream->next();
            $arguments = $this->ExpressionList();
        } else {
            $first = $this->Expression();
            if (Keyword::FROM === $this->stream->getKeyword()) {
                $this->stream->next();
                $arguments   = $this->ExpressionList();
                $arguments[] = $first;
            } else {
                $arguments = new nodes\lists\ExpressionList([$first]);
                if ($this->stream->matchesSpecialChar(',')) {
                    $this->stream->next();
                    $arguments->merge($this->ExpressionList());
                }
            }
        }

        return [$arguments, $side];
    }

    protected function OverlayExpressionFromArguments(): nodes\FunctionLike
    {
        if ($this->stream->matches(TokenType::SPECIAL_CHAR, ')')) {
            // This is an invocation of user-defined function named "overlay" with no arguments
            return new nodes\expressions\FunctionExpression(
                new nodes\QualifiedName('overlay'),
                new nodes\lists\FunctionArgumentList()
            );
        }
        switch ($this->checkContentsOfParentheses(-1)) {
            case self::PARENTHESES_ARGS:
            case self::PARENTHESES_ROW:
                // This is an invocation of user-defined function named "overlay" with generic arguments
                return new nodes\expressions\FunctionExpression(
                    new nodes\QualifiedName('overlay'),
                    $this->FunctionArgumentList()
                );

            default:
                // This may be either a user-defined function with a single argument or an SQL-standard one
                $arguments = [$this->Expression()];
                if ($this->stream->matches(TokenType::SPECIAL_CHAR, ')')) {
                    return new nodes\expressions\FunctionExpression(
                        new nodes\QualifiedName('overlay'),
                        new nodes\lists\FunctionArgumentList($arguments)
                    );
                }
                $this->stream->expectKeyword(Keyword::PLACING);
                $arguments[] = $this->Expression();
                $this->stream->expectKeyword(Keyword::FROM);
                $arguments[] = $this->Expression();
                if (Keyword::FOR === $this->stream->getKeyword()) {
                    $this->stream->next();
                    $arguments[] = $this->Expression();
                }
                return new nodes\expressions\OverlayExpression(...$arguments);
        }
    }

    protected function SubstringExpressionFromArguments(): nodes\FunctionLike
    {
        if ($this->stream->matches(TokenType::SPECIAL_CHAR, ')')) {
            // This is an invocation of user-defined function named "substring" with no arguments
            return new nodes\expressions\FunctionExpression(
                new nodes\QualifiedName('substring'),
                new nodes\lists\FunctionArgumentList()
            );
        }
        switch ($this->checkContentsOfParentheses(-1)) {
            case self::PARENTHESES_ARGS:
            case self::PARENTHESES_ROW:
                // This is an invocation of user-defined function named "substring" with generic arguments
                return new nodes\expressions\FunctionExpression(
                    new nodes\QualifiedName('substring'),
                    $this->FunctionArgumentList()
                );

            default:
                // This may be either a user-defined function with a single argument or an SQL-standard one
                $arguments = [$this->Expression()];
                if ($this->stream->matches(TokenType::SPECIAL_CHAR, ')')) {
                    return new nodes\expressions\FunctionExpression(
                        new nodes\QualifiedName('substring'),
                        new nodes\lists\FunctionArgumentList($arguments)
                    );
                }
                $keyword = $this->stream->expectKeyword(Keyword::FROM, Keyword::FOR, Keyword::SIMILAR);
                if (Keyword::SIMILAR === $keyword) {
                    $similar = $this->Expression();
                    $this->stream->expectKeyword(Keyword::ESCAPE);
                    return new nodes\expressions\SubstringSimilarExpression(
                        $arguments[0],
                        $similar,
                        $this->Expression()
                    );
                }
                $arguments[] = null;
                $arguments[] = null;
                if (Keyword::FROM === $keyword) {
                    $arguments[1] = $this->Expression();
                } else {
                    $arguments[2] = $this->Expression();
                }
                if (null !== $keyword = $this->stream->matchesAnyKeyword(Keyword::FROM, Keyword::FOR)) {
                    if (!$arguments[1] && Keyword::FROM === $keyword) {
                        $this->stream->next();
                        $arguments[1] = $this->Expression();

                    } elseif (!$arguments[2] && Keyword::FOR === $keyword) {
                        $this->stream->next();
                        $arguments[2] = $this->Expression();
                    }
                }
                return new nodes\expressions\SubstringFromExpression(...$arguments);
        }
    }

    protected function XmlElementFunction(): nodes\xml\XmlElement
    {
        $this->stream->expectKeyword(Keyword::NAME);
        $name = $this->ColLabel();
        $attributes = $content = null;
        if ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            if (Keyword::XMLATTRIBUTES !== $this->stream->getKeyword()) {
                $content = $this->ExpressionList();
            } else {
                $this->stream->next();
                $this->stream->expect(TokenType::SPECIAL_CHAR, '(');
                $attributes = new nodes\lists\TargetList($this->XmlAttributeList());
                $this->stream->expect(TokenType::SPECIAL_CHAR, ')');
                if ($this->stream->matchesSpecialChar(',')) {
                    $this->stream->next();
                    $content = $this->ExpressionList();
                }
            }
        }
        return new nodes\xml\XmlElement($name, $attributes, $content);
    }

    protected function XmlRoot(): nodes\xml\XmlRoot
    {
        $xml = $this->Expression();
        $this->stream->expect(TokenType::SPECIAL_CHAR, ',');
        $this->stream->expectKeyword(Keyword::VERSION);
        $version = $this->stream->matchesKeywordSequence(Keyword::NO, Keyword::VALUE) ? null : $this->Expression();
        if (!$this->stream->matchesSpecialChar(',')) {
            $standalone = null;
        } else {
            $this->stream->next();
            $this->stream->expectKeyword(Keyword::STANDALONE);
            if ($this->stream->matchesKeywordSequence(Keyword::NO, Keyword::VALUE)) {
                $this->stream->skip(2);
                $standalone = enums\XmlStandalone::NO_VALUE;
            } else {
                $standalone = enums\XmlStandalone::fromKeywords(
                    $this->stream->expectKeyword(Keyword::NO, Keyword::YES)
                );
            }
        }
        return new nodes\xml\XmlRoot($xml, $version, $standalone);
    }

    /**
     * @return nodes\TargetElement[]
     */
    protected function XmlAttributeList(): array
    {
        $attributes = [$this->XmlAttribute()];

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $attributes[] = $this->XmlAttribute();
        }

        return $attributes;
    }

    protected function XmlAttribute(): nodes\TargetElement
    {
        $value   = $this->Expression();
        $attName = null;
        if (Keyword::AS === $this->stream->getKeyword()) {
            $this->stream->next();
            $attName = $this->ColLabel();
        }
        return new nodes\TargetElement($value, $attName);
    }

    protected function convertSpecialFunctionCallToFunctionExpression(
        nodes\FunctionLike|nodes\ScalarExpression $function
    ): nodes\ScalarExpression {
        if ($function instanceof nodes\ScalarExpression) {
            return $function;
        } elseif ($function instanceof nodes\FunctionCall) {
            return new nodes\expressions\FunctionExpression(
                clone $function->name,
                clone $function->arguments,
                $function->distinct,
                $function->variadic,
                clone $function->order
            );
        }

        throw new exceptions\InvalidArgumentException(
            __FUNCTION__ . "() requires an instance of FunctionCall or ScalarExpression, "
            . $function::class . " given"
        );
    }

    protected function FunctionExpression(array $identifiers): nodes\ScalarExpression
    {
        $function    = $this->GenericFunctionCall($identifiers);
        $withinGroup = false;
        $order       = null;

        if ($this->stream->matchesKeywordSequence(Keyword::WITHIN, Keyword::GROUP)) {
            if (\count($function->order) > 0) {
                throw exceptions\SyntaxException::atPosition(
                    'Cannot use multiple ORDER BY clauses with WITHIN GROUP',
                    $this->stream->getSource(),
                    $this->stream->getCurrent()->getPosition()
                );
            }
            if ($function->distinct) {
                throw exceptions\SyntaxException::atPosition(
                    'Cannot use DISTINCT with WITHIN GROUP',
                    $this->stream->getSource(),
                    $this->stream->getCurrent()->getPosition()
                );
            }
            if ($function->variadic) {
                throw exceptions\SyntaxException::atPosition(
                    'Cannot use VARIADIC with WITHIN GROUP',
                    $this->stream->getSource(),
                    $this->stream->getCurrent()->getPosition()
                );
            }
            $withinGroup = true;
            $this->stream->skip(2);
            $this->stream->expect(TokenType::SPECIAL_CHAR, '(');
            $this->stream->expectKeyword(Keyword::ORDER);
            $this->stream->expectKeyword(Keyword::BY);
            $order = $this->OrderByList();
            $this->stream->expect(TokenType::SPECIAL_CHAR, ')');
        }

        return new nodes\expressions\FunctionExpression(
            clone $function->name,
            clone $function->arguments,
            $function->distinct,
            $function->variadic,
            $order ?: clone $function->order,
            $withinGroup,
            $this->FilterClause(),
            $this->OverClause()
        );
    }

    protected function FilterClause(): ?nodes\ScalarExpression
    {
        if (Keyword::FILTER !== $this->stream->getKeyword()) {
            return null;
        }

        $this->stream->next();
        $this->stream->expect(TokenType::SPECIAL_CHAR, '(');
        $this->stream->expectKeyword(Keyword::WHERE);
        $filter = $this->Expression();
        $this->stream->expect(TokenType::SPECIAL_CHAR, ')');

        return $filter;
    }

    protected function OverClause(): ?nodes\WindowDefinition
    {
        if (Keyword::OVER !== $this->stream->getKeyword()) {
            return null;
        }
        $this->stream->next();
        return $this->WindowSpecification();
    }

    protected function SpecialFunctionCall(): nodes\FunctionLike|nodes\ScalarExpression|null
    {
        $funcNode = $this->SQLValueFunction()
                    ?? $this->SystemFunctionCall();

        if (null === $funcNode && $this->stream->matchesKeywordSequence(Keyword::COLLATION, Keyword::FOR)) {
            $this->stream->skip(2);
            $this->stream->expect(TokenType::SPECIAL_CHAR, '(');
            $funcNode = new nodes\expressions\CollationForExpression($this->Expression());
            $this->stream->expect(TokenType::SPECIAL_CHAR, ')');
        }

        return $funcNode;
    }

    protected function GenericFunctionCall(array $identifiers = []): nodes\FunctionCall
    {
        $positionalArguments = $namedArguments = [];
        $variadic = $distinct = false;
        $orderBy  = null;

        $funcName = empty($identifiers) ? $this->GenericFunctionName() : new nodes\QualifiedName(...$identifiers);

        $this->stream->expect(TokenType::SPECIAL_CHAR, '(');
        if ($this->stream->matchesSpecialChar('*')) {
            $this->stream->next();
            $positionalArguments = new nodes\Star();

        } elseif (!$this->stream->matchesSpecialChar(')')) {
            if (null !== $keyword = $this->stream->matchesAnyKeyword(Keyword::DISTINCT, Keyword::ALL)) {
                $this->stream->next();
                $distinct = Keyword::DISTINCT === $keyword;
            }
            [$value, $name, $variadic] = $this->GenericFunctionArgument();
            if (!$name) {
                $positionalArguments[] = $value;
            } else {
                $namedArguments[(string)$name] = $value;
            }

            while (!$variadic && $this->stream->matchesSpecialChar(',')) {
                $this->stream->next();

                $argToken = $this->stream->getCurrent();
                [$value, $name, $variadic] = $this->GenericFunctionArgument();
                if (!$name) {
                    if (empty($namedArguments)) {
                        $positionalArguments[] = $value;
                    } else {
                        throw exceptions\SyntaxException::atPosition(
                            'Positional argument cannot follow named argument',
                            $this->stream->getSource(),
                            $argToken->getPosition()
                        );
                    }
                } elseif (!isset($namedArguments[(string)$name])) {
                    $namedArguments[(string)$name] = $value;
                } else {
                    throw exceptions\SyntaxException::atPosition(
                        "Argument name {$name} used more than once",
                        $this->stream->getSource(),
                        $argToken->getPosition()
                    );
                }
            }
            if ($this->stream->matchesKeywordSequence(Keyword::ORDER, Keyword::BY)) {
                $this->stream->skip(2);
                $orderBy = $this->OrderByList();
            }
        }
        $this->stream->expect(TokenType::SPECIAL_CHAR, ')');

        return new nodes\FunctionCall(
            $funcName,
            $positionalArguments instanceof nodes\Star
            ? $positionalArguments : new nodes\lists\FunctionArgumentList($positionalArguments + $namedArguments),
            $distinct,
            $variadic,
            $orderBy
        );
    }

    protected function GenericFunctionName(): nodes\QualifiedName
    {
        if (\in_array($this->stream->getCurrent()->getType(), self::ATOM_IDENTIFIER_TYPES, true)) {
            $firstToken = $this->stream->next();
        } else {
            // This will always throw an exception, as IDENTIFIER was processed in previous branch
            $firstToken = $this->stream->expect(TokenType::IDENTIFIER);
        }
        $funcName = [new nodes\Identifier($firstToken->getValue())];

        while ($this->stream->matchesSpecialChar('.')) {
            $this->stream->next();
            $funcName[] = $this->ColLabel();
        }

        if (
            TokenType::TYPE_FUNC_NAME_KEYWORD === $firstToken->getType() && 1 < \count($funcName)
            || TokenType::COL_NAME_KEYWORD === $firstToken->getType() && 1 === \count($funcName)
        ) {
            throw exceptions\SyntaxException::atPosition(
                \implode('.', $funcName) . ' is not a valid function name',
                $this->stream->getSource(),
                $firstToken->getPosition()
            );
        }

        return new nodes\QualifiedName(...$funcName);
    }

    /**
     * func_arg_list production from grammar, needed for substring() and friends
     */
    protected function FunctionArgumentList(): nodes\lists\FunctionArgumentList
    {
        $positionalArguments = $namedArguments = [];
        [$value, $name, ] = $this->GenericFunctionArgument(false);
        if (!$name) {
            $positionalArguments[] = $value;
        } else {
            $namedArguments[(string)$name] = $value;
        }

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();

            $argToken = $this->stream->getCurrent();
            [$value, $name, ] = $this->GenericFunctionArgument(false);
            if (!$name) {
                if (empty($namedArguments)) {
                    $positionalArguments[] = $value;
                } else {
                    throw exceptions\SyntaxException::atPosition(
                        'Positional argument cannot follow named argument',
                        $this->stream->getSource(),
                        $argToken->getPosition()
                    );
                }
            } elseif (!isset($namedArguments[(string)$name])) {
                $namedArguments[(string)$name] = $value;
            } else {
                throw exceptions\SyntaxException::atPosition(
                    "Argument name {$name} used more than once",
                    $this->stream->getSource(),
                    $argToken->getPosition()
                );
            }
        }

        return new nodes\lists\FunctionArgumentList($positionalArguments + $namedArguments);
    }

    /**
     * Parses (maybe named or variadic) function argument
     *
     * @param bool $allowVariadic Whether to check for VARIADIC keyword before the argument
     * @return array{nodes\ScalarExpression, ?nodes\Identifier, bool}
     */
    protected function GenericFunctionArgument(bool $allowVariadic = true): array
    {
        $variadic = false;
        if ($allowVariadic && ($variadic = (Keyword::VARIADIC === $this->stream->getKeyword()))) {
            $this->stream->next();
        }

        $name = null;
        // it's the only place this shit can appear in
        if (
            $this->stream->look(1)->matches(TokenType::COLON_EQUALS)
            || $this->stream->look(1)->matches(TokenType::EQUALS_GREATER)
        ) {
            if (
                $this->stream->matchesAnyType(
                    TokenType::IDENTIFIER,
                    TokenType::UNRESERVED_KEYWORD,
                    TokenType::TYPE_FUNC_NAME_KEYWORD
                )
            ) {
                $name = new nodes\Identifier($this->stream->next()->getValue());
            } else {
                // This will always throw an exception, as IDENTIFIER was processed in previous branch
                $this->stream->expect(TokenType::IDENTIFIER);
            }
            $this->stream->next();
        }

        return [$this->Expression(), $name, $variadic];
    }

    /**
     * @param bool $allowStar Whether to allow Star nodes in returned array
     * @return array<nodes\Identifier|nodes\ArrayIndexes|nodes\Star>
     */
    protected function Indirection(bool $allowStar = true): array
    {
        $indirection = [];
        while ($this->stream->matchesSpecialChar(['[', '.'])) {
            if ('.' === $this->stream->next()->getValue()) {
                if (!$this->stream->matchesSpecialChar('*')) {
                    $indirection[] = $this->ColLabel();
                } elseif (!$allowStar) {
                    // this will basically trigger an error if '.*' appears in list of fields for INSERT or UPDATE
                    $this->stream->expect(TokenType::IDENTIFIER);
                } else {
                    $this->stream->next();
                    $indirection[] = new nodes\Star();
                    break;
                }

            } else {
                $lower = $upper = null;
                $isSlice = false;

                if (!$this->stream->matchesSpecialChar(':')) {
                    $lower = $this->Expression();
                }
                if ($this->stream->matchesSpecialChar(':')) {
                    $this->stream->next();
                    $isSlice = true;
                    if (!$this->stream->matchesSpecialChar(']')) {
                        $upper = $this->Expression();
                    }
                }
                $this->stream->expect(TokenType::SPECIAL_CHAR, ']');

                $indirection[] = $isSlice
                                 ? new nodes\ArrayIndexes($lower, $upper, true)
                                 : new nodes\ArrayIndexes(null, $lower);
            }
        }
        return $indirection;
    }

    protected function TargetList(): nodes\lists\TargetList
    {
        $elements = new nodes\lists\TargetList([$this->TargetElement()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $elements[] = $this->TargetElement();
        }

        return $elements;
    }

    protected function TargetElement(): nodes\Star|nodes\TargetElement
    {
        $alias = null;

        if ($this->stream->matchesSpecialChar('*')) {
            $this->stream->next();
            return new nodes\Star();
        }
        $element = $this->Expression(true);
        if (
            $this->stream->matches(TokenType::IDENTIFIER)
            || (null !== $keyword = $this->stream->getKeyword())
                && $keyword->isBareLabel()
        ) {
            $alias = new nodes\Identifier($this->stream->next()->getValue());

        } elseif (Keyword::AS === $this->stream->getKeyword()) {
            $this->stream->next();
            $alias = $this->ColLabel();
        }

        return new nodes\TargetElement($element, $alias);
    }

    protected function FromList(): nodes\lists\FromList
    {
        $relations = new nodes\lists\FromList([$this->FromElement()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $relations[] = $this->FromElement();
        }

        return $relations;
    }

    protected function FromElement(): nodes\range\FromElement
    {
        $left = $this->TableReference();

        while (
            null !== $keyword = $this->stream->matchesAnyKeyword(
                Keyword::CROSS,
                Keyword::NATURAL,
                Keyword::LEFT,
                Keyword::RIGHT,
                Keyword::FULL,
                Keyword::INNER,
                Keyword::JOIN
            )
        ) {
            // CROSS JOIN needs no join quals
            if (Keyword::CROSS === $keyword) {
                $this->stream->next();
                $this->stream->expectKeyword(Keyword::JOIN);
                $left = new nodes\range\JoinExpression($left, $this->TableReference(), enums\JoinType::CROSS);
                continue;
            }
            if (Keyword::NATURAL === $keyword) {
                $this->stream->next();
                $natural = true;
            } else {
                $natural = false;
            }

            if (Keyword::JOIN === $this->stream->getKeyword()) {
                $this->stream->next();
                $joinType = enums\JoinType::INNER;
            } else {
                $joinType = enums\JoinType::fromKeywords(
                    $this->stream->expectKeyword(Keyword::LEFT, Keyword::RIGHT, Keyword::FULL, Keyword::INNER)
                );
                // noise word
                if (Keyword::OUTER === $this->stream->getKeyword()) {
                    $this->stream->next();
                }
                $this->stream->expectKeyword(Keyword::JOIN);
            }
            $left = new nodes\range\JoinExpression($left, $this->TableReference(), $joinType);

            if ($natural) {
                $left->setNatural(true);

            } else {
                $keyword = $this->stream->expectKeyword(Keyword::ON, Keyword::USING);
                if (Keyword::ON === $keyword) {
                    $left->setOn($this->Expression());
                } else {
                    $left->setUsing($this->UsingClause(true));
                }
            }
        }

        return $left;
    }

    /**
     * Parses the USING clause of JOIN expression
     *
     * Setting $parentheses to false allows to parse the clause as ColIdList, rather than unconditionally
     * fail if the stream is not on the opening parenthesis. This is mostly needed for BC.
     *
     * @param bool $parentheses whether to require parentheses
     */
    protected function UsingClause(bool $parentheses = false): nodes\range\UsingClause
    {
        if ($parentheses) {
            $this->stream->expect(TokenType::SPECIAL_CHAR, '(');
        } elseif ($this->stream->matches(TokenType::SPECIAL_CHAR, '(')) {
            $parentheses = true;
            $this->stream->next();
        }

        $alias = null;
        $items = $this->ColIdList();

        if ($parentheses) {
            $this->stream->expect(TokenType::SPECIAL_CHAR, ')');
            if (Keyword::AS === $this->stream->getKeyword()) {
                $this->stream->next();
                $alias = $this->ColId();
            }
        }

        return new nodes\range\UsingClause($items, $alias);
    }

    protected function TableReference(): nodes\range\FromElement
    {
        if (Keyword::LATERAL === $this->stream->getKeyword()) {
            $this->stream->next();
            // lateral can only apply to subselects, XMLTABLEs or function invocations
            if ($this->stream->matchesSpecialChar('(')) {
                $reference = $this->RangeSubselect();
            } elseif (Keyword::XMLTABLE === $this->stream->getKeyword()) {
                $reference = $this->XmlTable();
            } elseif (Keyword::JSON_TABLE === $this->stream->getKeyword()) {
                $reference = $this->JsonTable();
            } else {
                $reference = $this->RangeFunctionCall();
            }
            $reference->setLateral(true);

        } elseif ($this->stream->matchesSpecialChar('(')) {
            // parentheses may contain either a subselect or JOIN expression
            if (self::PARENTHESES_SELECT === $this->checkContentsOfParentheses()) {
                $reference = $this->RangeSubselect();
            } else {
                $this->stream->next();
                $reference = $this->FromElement();
                $this->stream->expect(TokenType::SPECIAL_CHAR, ')');
                if ($alias = $this->OptionalAliasClause()) {
                    $reference->setAlias($alias[0], $alias[1]);
                }
            }

        } elseif (Keyword::XMLTABLE === $this->stream->getKeyword()) {
            $reference = $this->XmlTable();

        } elseif (Keyword::JSON_TABLE === $this->stream->getKeyword()) {
            $reference = $this->JsonTable();

        } elseif (
            $this->stream->matchesKeywordSequence(Keyword::ROWS, Keyword::FROM)
                  || $this->matchesFunctionCall()
        ) {
            $reference = $this->RangeFunctionCall();

        } else {
            $reference = $this->RelationExpression();
        }

        return $reference;
    }

    protected function RangeSubselect(): nodes\range\Subselect
    {
        $reference = new nodes\range\Subselect($this->SelectWithParentheses());

        if (null !== ($alias = $this->OptionalAliasClause())) {
            $reference->setAlias($alias[0], $alias[1]);
        }

        return $reference;
    }

    protected function RangeFunctionCall(): nodes\range\FunctionFromElement
    {
        if (!$this->stream->matchesKeywordSequence(Keyword::ROWS, Keyword::FROM)) {
            // gram.y allows JSON aggregates and similar stuff here, but they are rejected by C code afterwards
            $token    = $this->stream->getCurrent();
            $function = $this->SpecialFunctionCall()
                ?? $this->JsonAggregateFunc(true)
                ?? $this->GenericFunctionCall();
            if (!$function instanceof nodes\FunctionLike) {
                throw exceptions\SyntaxException::atPosition(
                    "Expression cannot be used in FROM clause",
                    $this->stream->getSource(),
                    $token->getPosition()
                );
            }
            $reference = new nodes\range\FunctionCall($function);
        } else {
            $this->stream->skip(2);
            $this->stream->expect(TokenType::SPECIAL_CHAR, '(');
            $list = new nodes\lists\RowsFromList([$this->RowsFromElement()]);
            while ($this->stream->matchesSpecialChar(',')) {
                $this->stream->next();
                $list[] = $this->RowsFromElement();
            }
            $this->stream->expect(TokenType::SPECIAL_CHAR, ')');

            $reference = new nodes\range\RowsFrom($list);
        }

        if ($this->stream->matchesKeywordSequence(Keyword::WITH, Keyword::ORDINALITY)) {
            $this->stream->skip(2);
            $reference->setWithOrdinality(true);
        }

        if ($alias = $this->OptionalAliasClause(true)) {
            $reference->setAlias($alias[0], $alias[1]);
        }

        return $reference;
    }

    protected function RowsFromElement(): nodes\range\RowsFromElement
    {
        $token    = $this->stream->getCurrent();
        $function = $this->SpecialFunctionCall()
            ?? $this->JsonAggregateFunc(true)
            ?? $this->GenericFunctionCall();
        if (!$function instanceof nodes\FunctionLike) {
            throw exceptions\SyntaxException::atPosition(
                "Expression cannot be used in FROM clause",
                $this->stream->getSource(),
                $token->getPosition()
            );
        }

        if (Keyword::AS !== $this->stream->getKeyword()) {
            $aliases = null;
        } else {
            $this->stream->next();
            $this->stream->expect(TokenType::SPECIAL_CHAR, '(');
            $aliases = new nodes\lists\ColumnDefinitionList([$this->TableFuncElement()]);
            while ($this->stream->matchesSpecialChar(',')) {
                $this->stream->next();
                $aliases[] = $this->TableFuncElement();
            }
            $this->stream->expect(TokenType::SPECIAL_CHAR, ')');
        }
        return new nodes\range\RowsFromElement($function, $aliases);
    }

    /**
     * relation_expr_opt_alias from grammar, with no special case for SET
     *
     * Not used in parser itself, needed by StatementFactory
     */
    protected function RelationExpressionOptAlias(): nodes\range\UpdateOrDeleteTarget
    {
        return $this->UpdateOrDeleteTarget(self::RELATION_FORMAT_DELETE);
    }

    protected function InsertTarget(): nodes\range\InsertTarget
    {
        $name  = $this->QualifiedName();
        $alias = null;

        // AS is required for aliases in INSERT
        if (Keyword::AS === $this->stream->getKeyword()) {
            $this->stream->next();
            $alias = $this->ColId();
        }

        return new nodes\range\InsertTarget($name, $alias);
    }

    protected function RelationExpression(): nodes\range\RelationReference|nodes\range\TableSample
    {
        $expression = new nodes\range\RelationReference(...$this->QualifiedNameWithInheritOption());

        if ($alias = $this->OptionalAliasClause()) {
            $expression->setAlias($alias[0], $alias[1]);
        }

        if (Keyword::TABLESAMPLE === $this->stream->getKeyword()) {
            $this->stream->next();
            $method     = $this->GenericFunctionName();
            $this->stream->expect(TokenType::SPECIAL_CHAR, '(');
            $arguments  = $this->ExpressionList();
            $this->stream->expect(TokenType::SPECIAL_CHAR, ')');

            $repeatable = null;
            if (Keyword::REPEATABLE === $this->stream->getKeyword()) {
                $this->stream->next();
                $this->stream->expect(TokenType::SPECIAL_CHAR, '(');
                $repeatable = $this->Expression();
                $this->stream->expect(TokenType::SPECIAL_CHAR, ')');
            }

            $expression = new nodes\range\TableSample($expression, $method, $arguments, $repeatable);
        }

        return $expression;
    }

    protected function UpdateOrDeleteTarget(
        string $statementType = self::RELATION_FORMAT_UPDATE
    ): nodes\range\UpdateOrDeleteTarget {
        [$name, $inherit] = $this->QualifiedNameWithInheritOption();
        return new nodes\range\UpdateOrDeleteTarget(
            $name,
            $this->DMLAliasClause($statementType),
            $inherit
        );
    }

    /**
     * Common part of relation reference for SELECT / UPDATE / DELETE statements
     *
     * @return array{nodes\QualifiedName, bool|null}
     */
    protected function QualifiedNameWithInheritOption(): array
    {
        $inherit           = null;
        $expectParenthesis = false;
        if (Keyword::ONLY === $this->stream->getKeyword()) {
            $this->stream->next();
            $inherit = false;
            if ($this->stream->matchesSpecialChar('(')) {
                $expectParenthesis = true;
                $this->stream->next();
            }
        }

        $name = $this->QualifiedName();

        if (false === $inherit && $expectParenthesis) {
            $this->stream->expect(TokenType::SPECIAL_CHAR, ')');

        } elseif (null === $inherit && $this->stream->matchesSpecialChar('*')) {
            $this->stream->next();
            $inherit = true;
        }

        return [$name, $inherit];
    }

    /**
     * Corresponds to relation_expr_opt_alias production from grammar, see the comment there.
     */
    protected function DMLAliasClause(string $statementType): ?nodes\Identifier
    {
        if (
            Keyword::AS === $this->stream->getKeyword()
            || $this->stream->matchesAnyType(TokenType::IDENTIFIER, TokenType::COL_NAME_KEYWORD)
            || ($this->stream->matches(TokenType::UNRESERVED_KEYWORD)
                && (self::RELATION_FORMAT_UPDATE !== $statementType
                    || Keyword::SET !== $this->stream->getCurrent()->getKeyword()))
        ) {
            if (Keyword::AS === $this->stream->getKeyword()) {
                $this->stream->next();
            }
            return $this->ColId();
        }
        return null;
    }

    protected function OptionalAliasClause(bool $allowFunctionAlias = false): ?array
    {
        if (
            Keyword::AS === $this->stream->getKeyword()
            || $this->stream->matchesAnyType(
                TokenType::IDENTIFIER,
                TokenType::UNRESERVED_KEYWORD,
                TokenType::COL_NAME_KEYWORD
            )
        ) {
            $tableAlias    = null;
            $columnAliases = null;

            // AS is complete noise here, unlike in TargetList
            if (Keyword::AS === $this->stream->getKeyword()) {
                $this->stream->next();
            }
            if (!$allowFunctionAlias || !$this->stream->matchesSpecialChar('(')) {
                $tableAlias = $this->ColId();
            }
            if (!$tableAlias || $this->stream->matchesSpecialChar('(')) {
                $this->stream->expect(TokenType::SPECIAL_CHAR, '(');

                if (
                    $allowFunctionAlias
                    // for TableFuncElement the next position will contain typename
                    && (!$tableAlias || !$this->stream->look()->matches(TokenType::SPECIAL_CHAR, [')', ',']))
                ) {
                    $columnAliases = new nodes\lists\ColumnDefinitionList([$this->TableFuncElement()]);
                    while ($this->stream->matchesSpecialChar(',')) {
                        $this->stream->next();
                        $columnAliases[] = $this->TableFuncElement();
                    }
                } else {
                    $columnAliases = new nodes\lists\IdentifierList([$this->ColId()]);
                    while ($this->stream->matchesSpecialChar(',')) {
                        $this->stream->next();
                        $columnAliases[] = $this->ColId();
                    }
                }

                $this->stream->expect(TokenType::SPECIAL_CHAR, ')');
            }

            return [$tableAlias, $columnAliases];
        }
        return null;
    }

    protected function TableFuncElement(): nodes\range\ColumnDefinition
    {
        $alias     = $this->ColId();
        $type      = $this->TypeName();
        $collation = null;
        if (Keyword::COLLATE === $this->stream->getKeyword()) {
            $this->stream->next();
            $collation = $this->QualifiedName();
        }

        return new nodes\range\ColumnDefinition($alias, $type, $collation);
    }

    protected function ColIdList(): nodes\lists\IdentifierList
    {
        $list = new nodes\lists\IdentifierList([$this->ColId()]);
        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $list[] = $this->ColId();
        }
        return $list;
    }

    /**
     * ColId production from Postgres grammar
     */
    protected function ColId(): nodes\Identifier
    {
        if (
            $this->stream->matchesAnyType(
                TokenType::IDENTIFIER,
                TokenType::UNRESERVED_KEYWORD,
                TokenType::COL_NAME_KEYWORD
            )
        ) {
            $token = $this->stream->next();
        } else {
            // This will throw an exception as IDENTIFIER was matched in previous branch
            $token = $this->stream->expect(TokenType::IDENTIFIER);
        }
        return new nodes\Identifier($token->getValue());
    }

    /**
     * ColLabel production from Postgres grammar - allow identifiers and keywords of *any* type
     */
    protected function ColLabel(): nodes\Identifier
    {
        if ($this->stream->matchesAnyType(TokenType::IDENTIFIER, TokenType::KEYWORD)) {
            $token = $this->stream->next();
        } else {
            // This will throw an exception as IDENTIFIER was matched in previous branch
            $token = $this->stream->expect(TokenType::IDENTIFIER);
        }
        return new nodes\Identifier($token->getValue());
    }

    protected function QualifiedName(): nodes\QualifiedName
    {
        $parts = [$this->ColId()];

        while ($this->stream->matchesSpecialChar('.')) {
            $this->stream->next();
            $parts[] = $this->ColLabel();
        }

        return new nodes\QualifiedName(...$parts);
    }

    protected function OrderByList(): nodes\lists\OrderByList
    {
        $items = new nodes\lists\OrderByList([$this->OrderByElement()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $items[] = $this->OrderByElement();
        }

        return $items;
    }

    protected function OrderByElement(): nodes\OrderByElement
    {
        $expression = $this->Expression();
        $operator   = null;
        $direction  = null;
        $nullsOrder = null;
        if (null !== $keyword = $this->stream->matchesAnyKeyword(Keyword::ASC, Keyword::DESC, Keyword::USING)) {
            $this->stream->next();
            $direction = enums\OrderByDirection::fromKeywords($keyword);
            if (Keyword::USING === $keyword) {
                $operator = $this->Operator(true);
            }
        }
        if (Keyword::NULLS === $this->stream->getKeyword()) {
            $this->stream->next();
            $nullsOrder = enums\NullsOrder::fromKeywords(
                $this->stream->expectKeyword(Keyword::FIRST, Keyword::LAST)
            );
        }

        return new nodes\OrderByElement($expression, $direction, $nullsOrder, $operator);
    }

    protected function OnConflict(): nodes\OnConflictClause
    {
        $target    = null;
        $set       = null;
        $condition = null;
        if ($this->stream->matchesKeywordSequence(Keyword::ON, Keyword::CONSTRAINT)) {
            $this->stream->skip(2);
            $target = $this->ColId();

        } elseif ($this->stream->matchesSpecialChar('(')) {
            $target = $this->IndexParameters();
        }

        $this->stream->expectKeyword(Keyword::DO);
        if (Keyword::UPDATE === $action = $this->stream->expectKeyword(Keyword::UPDATE, Keyword::NOTHING)) {
            $this->stream->expectKeyword(Keyword::SET);
            $set = $this->SetClauseList();
            if (Keyword::WHERE === $this->stream->getKeyword()) {
                $this->stream->next();
                $condition = $this->Expression();
            }
        }

        return new nodes\OnConflictClause(enums\OnConflictAction::fromKeywords($action), $target, $set, $condition);
    }

    protected function IndexParameters(): nodes\IndexParameters
    {
        $this->stream->expect(TokenType::SPECIAL_CHAR, '(');

        $items = new nodes\IndexParameters([$this->IndexElement()]);
        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $items[] = $this->IndexElement();
        }

        $this->stream->expect(TokenType::SPECIAL_CHAR, ')');

        if (Keyword::WHERE === $this->stream->getKeyword()) {
            $this->stream->next();
            $items->where->condition = $this->Expression();
        }

        return $items;
    }


    protected function IndexElement(): nodes\IndexElement
    {
        if ($this->stream->matchesSpecialChar('(')) {
            $this->stream->next();
            $expression = $this->Expression();
            $this->stream->expect(TokenType::SPECIAL_CHAR, ')');

        } elseif ($this->matchesFunctionCall()) {
            /** @var nodes\FunctionCall $function */
            $function = $this->SpecialFunctionCall()
                ?? $this->JsonAggregateFunc(true)
                ?? $this->GenericFunctionCall();
            $expression = $this->convertSpecialFunctionCallToFunctionExpression($function);

        } else {
            $expression = $this->ColId();
        }

        $collation  = null;
        $opClass    = null;
        $direction  = null;
        $nullsOrder = null;

        if (Keyword::COLLATE === $this->stream->getKeyword()) {
            $this->stream->next();
            $collation = $this->QualifiedName();
        }

        if (
            $this->stream->matchesAnyType(
                TokenType::IDENTIFIER,
                TokenType::UNRESERVED_KEYWORD,
                TokenType::COL_NAME_KEYWORD
            )
        ) {
            $opClass = $this->QualifiedName();
        }

        if (null !== $keyword = $this->stream->matchesAnyKeyword(Keyword::ASC, Keyword::DESC)) {
            $this->stream->next();
            $direction = enums\IndexElementDirection::fromKeywords($keyword);
        }

        if (Keyword::NULLS === $this->stream->getKeyword()) {
            $this->stream->next();
            $nullsOrder = enums\NullsOrder::fromKeywords(
                $this->stream->expectKeyword(Keyword::FIRST, Keyword::LAST)
            );
        }

        return new nodes\IndexElement($expression, $collation, $opClass, $direction, $nullsOrder);
    }

    protected function GroupByClause(): nodes\group\GroupByClause
    {
        $distinct = $this->stream->matchesAnyKeyword(Keyword::ALL, Keyword::DISTINCT)
                    && 'distinct' === $this->stream->next()->getValue();
        $this->GroupByListElements($clause = new nodes\group\GroupByClause(null, $distinct));
        return $clause;
    }

    protected function GroupByListElements(nodes\lists\GroupByList $list): void
    {
        $list[] = $this->GroupByElement();

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $list[] = $this->GroupByElement();
        }
    }

    protected function GroupByElement(): nodes\group\GroupByElement|nodes\ScalarExpression
    {
        if (
            $this->stream->matchesSpecialChar('(')
            && $this->stream->look()->matches(TokenType::SPECIAL_CHAR, ')')
        ) {
            $this->stream->skip(2);
            $element = new nodes\group\EmptyGroupingSet();

        } elseif (null !== $type = $this->stream->matchesAnyKeyword(Keyword::CUBE, Keyword::ROLLUP)) {
            $this->stream->next();
            $this->stream->expect(TokenType::SPECIAL_CHAR, '(');
            $element = new nodes\group\CubeOrRollupClause(
                $this->ExpressionList(),
                enums\CubeOrRollup::fromKeywords($type)
            );
            $this->stream->expect(TokenType::SPECIAL_CHAR, ')');

        } elseif ($this->stream->matchesKeywordSequence(Keyword::GROUPING, Keyword::SETS)) {
            $this->stream->skip(2);
            $this->stream->expect(TokenType::SPECIAL_CHAR, '(');
            $this->GroupByListElements($element = new nodes\group\GroupingSetsClause());
            $this->stream->expect(TokenType::SPECIAL_CHAR, ')');

        } else {
            $element = $this->Expression();
        }

        return $element;
    }

    /**
     * @return nodes\ScalarExpression[]
     */
    protected function XmlExistsArguments(): array
    {
        $arguments = [$this->ExpressionAtom()];
        // 'by ref' is noise in Postgres
        $this->stream->expectKeyword(Keyword::PASSING);
        if (Keyword::BY === $this->stream->getKeyword()) {
            $this->stream->next();
            $this->stream->expectKeyword(Keyword::REF, Keyword::VALUE);
        }
        $arguments[] = $this->ExpressionAtom();
        if (Keyword::BY === $this->stream->getKeyword()) {
            $this->stream->next();
            $this->stream->expectKeyword(Keyword::REF, Keyword::VALUE);
        }

        return $arguments;
    }

    protected function XmlTable(): nodes\range\XmlTable
    {
        $this->stream->expectKeyword(Keyword::XMLTABLE);
        $this->stream->expect(TokenType::SPECIAL_CHAR, '(');

        $namespaces = null;
        if (Keyword::XMLNAMESPACES === $this->stream->getKeyword()) {
            $this->stream->next();
            $this->stream->expect(TokenType::SPECIAL_CHAR, '(');
            $namespaces = $this->XmlNamespaceList();
            $this->stream->expect(TokenType::SPECIAL_CHAR, ')');
            $this->stream->expect(TokenType::SPECIAL_CHAR, ',');
        }
        $doc = $this->XmlExistsArguments();
        $this->stream->expectKeyword(Keyword::COLUMNS);
        $columns = $this->XmlColumnList();

        $table = new nodes\range\XmlTable($doc[0], $doc[1], $columns, $namespaces);

        $this->stream->expect(TokenType::SPECIAL_CHAR, ')');

        if ($alias = $this->OptionalAliasClause()) {
            $table->setAlias($alias[0], $alias[1]);
        }

        return $table;
    }

    protected function XmlNamespaceList(): nodes\xml\XmlNamespaceList
    {
        $items = new nodes\xml\XmlNamespaceList([$this->XmlNamespace()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $items[] = $this->XmlNamespace();
        }

        return $items;
    }

    protected function XmlNamespace(): nodes\xml\XmlNamespace
    {
        // Default namespace is not currently supported, but Postgres accepts the syntax. We do the same.
        if (Keyword::DEFAULT === $this->stream->getKeyword()) {
            $this->stream->next();
            $value = $this->RestrictedExpression();
            $alias = null;

        } else {
            $value = $this->RestrictedExpression();
            $this->stream->expectKeyword(Keyword::AS);
            $alias = $this->ColLabel();
        }

        return new nodes\xml\XmlNamespace($value, $alias);
    }

    protected function XmlColumnList(): nodes\xml\XmlColumnList
    {
        $columns = new nodes\xml\XmlColumnList([$this->XmlColumnDefinition()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $columns[] = $this->XmlColumnDefinition();
        }

        return $columns;
    }

    protected function XmlColumnDefinition(): nodes\xml\XmlColumnDefinition
    {
        $name = $this->ColId();

        if ($this->stream->matchesKeywordSequence(Keyword::FOR, Keyword::ORDINALITY)) {
            $this->stream->skip(2);
            return new nodes\xml\XmlOrdinalityColumnDefinition($name);
        }

        $type = $this->TypeName();
        $nullable = $default = $path = null;
        do {
            if (Keyword::PATH === $this->stream->getKeyword()) {
                if (null !== $path) {
                    throw exceptions\SyntaxException::atPosition(
                        "only one PATH value per column is allowed",
                        $this->stream->getSource(),
                        $this->stream->getCurrent()->getPosition()
                    );
                }
                $this->stream->next();
                $path = $this->RestrictedExpression();

            } elseif (Keyword::DEFAULT === $this->stream->getKeyword()) {
                if (null !== $default) {
                    throw exceptions\SyntaxException::atPosition(
                        "only one DEFAULT value is allowed",
                        $this->stream->getSource(),
                        $this->stream->getCurrent()->getPosition()
                    );
                }
                $this->stream->next();
                $default = $this->RestrictedExpression();

            } elseif (Keyword::NULL === $this->stream->getKeyword()) {
                if (null !== $nullable) {
                    throw exceptions\SyntaxException::atPosition(
                        "conflicting or redundant NULL / NOT NULL declarations",
                        $this->stream->getSource(),
                        $this->stream->getCurrent()->getPosition()
                    );
                }
                $this->stream->next();
                $nullable = true;

            } elseif (
                Keyword::NOT === $this->stream->getKeyword()
                    && Keyword::NULL === $this->stream->look()->getKeyword()
            ) {
                if (null !== $nullable) {
                    throw exceptions\SyntaxException::atPosition(
                        "conflicting or redundant NULL / NOT NULL declarations",
                        $this->stream->getSource(),
                        $this->stream->getCurrent()->getPosition()
                    );
                }
                $this->stream->skip(2);
                $nullable = false;

            } else {
                break;
            }

        } while (true);

        return new nodes\xml\XmlTypedColumnDefinition($name, $type, $path, $nullable, $default);
    }

    protected function MergeStatement(): Merge
    {
        if (Keyword::WITH === $this->stream->getKeyword()) {
            $withClause = $this->WithClause();
        }

        $this->stream->expectKeyword(Keyword::MERGE);
        $this->stream->expectKeyword(Keyword::INTO);
        $relation = $this->UpdateOrDeleteTarget(self::RELATION_FORMAT_DELETE);

        $this->stream->expectKeyword(Keyword::USING);
        $using = $this->FromElement();

        $this->stream->expectKeyword(Keyword::ON);

        $merge = new Merge($relation, $using, $this->Expression(), $this->MergeWhenList());

        if (Keyword::RETURNING === $this->stream->getKeyword()) {
            $this->stream->next();
            $merge->returning->replace($this->TargetList());
        }

        if (!empty($withClause)) {
            $merge->with = $withClause;
        }
        return $merge;
    }

    protected function MergeWhenList(): nodes\merge\MergeWhenList
    {
        $list = new nodes\merge\MergeWhenList([$this->MergeWhenClause()]);
        while (Keyword::WHEN === $this->stream->getKeyword()) {
            $list[] = $this->MergeWhenClause();
        }
        return $list;
    }

    protected function MergeWhenClause(): nodes\merge\MergeWhenClause
    {
        $this->stream->expectKeyword(Keyword::WHEN);
        if (Keyword::NOT !== $this->stream->getKeyword()) {
            $matched = true;
        } else {
            $matched = false;
            $this->stream->next();
        }
        $this->stream->expectKeyword(Keyword::MATCHED);

        $matchedBySource = true;
        if (!$matched && $this->stream->matchesKeywordSequence(Keyword::BY, [Keyword::SOURCE, Keyword::TARGET])) {
            $this->stream->next();
            // "BY TARGET" is noise, "BY SOURCE" should generate MergeWhenMatched instead of MergeWhenNotMatched
            if (Keyword::SOURCE === $this->stream->next()->getKeyword()) {
                $matched         = true;
                $matchedBySource = false;
            }
        }

        if (Keyword::AND !== $this->stream->getKeyword()) {
            $condition = null;
        } else {
            $this->stream->next();
            $condition = $this->Expression();
        }

        $this->stream->expectKeyword(Keyword::THEN);
        return $matched
            ? new nodes\merge\MergeWhenMatched($condition, $this->MergeWhenMatchedAction(), $matchedBySource)
            : new nodes\merge\MergeWhenNotMatched($condition, $this->MergeWhenNotMatchedAction());
    }

    protected function MergeWhenMatchedAction(): null|nodes\merge\MergeDelete|nodes\merge\MergeUpdate
    {
        switch ($this->stream->expectKeyword(Keyword::DO, Keyword::DELETE, Keyword::UPDATE)) {
            case Keyword::DO:
                $this->stream->expectKeyword(Keyword::NOTHING);
                return null;

            case Keyword::DELETE:
                return new nodes\merge\MergeDelete();

            case Keyword::UPDATE:
            default:
                $this->stream->expectKeyword(Keyword::SET);
                return new nodes\merge\MergeUpdate($this->SetClauseList());
        }
    }

    protected function MergeWhenNotMatchedAction(): ?nodes\merge\MergeInsert
    {
        if (Keyword::DO === $this->stream->expectKeyword(Keyword::DO, Keyword::INSERT)) {
            $this->stream->expectKeyword(Keyword::NOTHING);
            return null;
        }

        if ($this->stream->matchesKeywordSequence(Keyword::DEFAULT, Keyword::VALUES)) {
            $this->stream->skip(2);
            return new nodes\merge\MergeInsert();
        }

        if (!$this->stream->matches(TokenType::SPECIAL_CHAR, '(')) {
            $cols = null;
        } else {
            $this->stream->next();
            $cols = $this->InsertTargetList();
            $this->stream->expect(TokenType::SPECIAL_CHAR, ')');
        }

        if (Keyword::OVERRIDING !== $this->stream->getKeyword()) {
            $overriding = null;
        } else {
            $this->stream->next();
            $overriding = enums\InsertOverriding::fromKeywords(
                $this->stream->expectKeyword(Keyword::USER, Keyword::SYSTEM)
            );
            $this->stream->expectKeyword(Keyword::VALUE);
        }

        return new nodes\merge\MergeInsert($cols, $this->MergeValues(), $overriding);
    }

    protected function MergeValues(): nodes\merge\MergeValues
    {
        $this->stream->expectKeyword(Keyword::VALUES);
        $this->stream->expect(TokenType::SPECIAL_CHAR, '(');
        $list = $this->ExpressionListWithDefault();
        $this->stream->expect(TokenType::SPECIAL_CHAR, ')');

        return new nodes\merge\MergeValues($list);
    }

    protected function JsonUniquenessConstraint(): ?bool
    {
        if (null === $keyword = $this->stream->matchesAnyKeyword(Keyword::WITH, Keyword::WITHOUT)) {
            return null;
        }

        $this->stream->next();
        $uniqueKeys = Keyword::WITH === $keyword;
        $this->stream->expectKeyword(Keyword::UNIQUE);
        if (Keyword::KEYS === $this->stream->getKeyword()) {
            $this->stream->next();
        }

        return $uniqueKeys;
    }

    protected function JsonNullClause(): ?bool
    {
        if (null === $keyword = $this->stream->matchesAnyKeyword(Keyword::ABSENT, Keyword::NULL)) {
            return null;
        }

        $this->stream->next();
        $absent = Keyword::ABSENT === $keyword;
        $this->stream->expectKeyword(Keyword::ON);
        $this->stream->expectKeyword(Keyword::NULL);

        return $absent;
    }

    protected function JsonFormat(): ?nodes\json\JsonFormat
    {
        if (!$this->stream->matchesKeywordSequence(Keyword::FORMAT, Keyword::JSON)) {
            return null;
        }

        $this->stream->skip(2);
        if (Keyword::ENCODING !== $this->stream->getKeyword()) {
            $encoding = null;
        } else {
            $this->stream->next();
            $encodingToken = $this->stream->getCurrent();
            $encodingValue = $this->ColId()->value;
            if (null === $encoding = enums\JsonEncoding::tryFrom(\strtolower($encodingValue))) {
                throw exceptions\SyntaxException::atPosition(
                    \sprintf('Unrecognized JSON encoding: %s"', $encodingValue),
                    $this->stream->getSource(),
                    $encodingToken->getPosition()
                );
            }
        }
        return new nodes\json\JsonFormat($encoding);
    }

    protected function JsonKeyValue(): nodes\json\JsonKeyValue
    {
        $key = $this->Expression();
        if (Keyword::VALUE !== $this->stream->getKeyword()) {
            $this->stream->expect(TokenType::SPECIAL_CHAR, ':');
        } else {
            $this->stream->next();
        }

        return new nodes\json\JsonKeyValue($key, $this->JsonFormattedValue());
    }

    protected function JsonFormattedValue(): nodes\json\JsonFormattedValue
    {
        return new nodes\json\JsonFormattedValue($this->Expression(), $this->JsonFormat());
    }

    protected function JsonArgument(): nodes\json\JsonArgument
    {
        $value = $this->JsonFormattedValue();
        $this->stream->expectKeyword(Keyword::AS);
        return new nodes\json\JsonArgument($value, $this->ColLabel());
    }

    protected function JsonReturningClause(): ?nodes\json\JsonReturning
    {
        if (Keyword::RETURNING !== $this->stream->getKeyword()) {
            return null;
        }

        $this->stream->next();
        return new nodes\json\JsonReturning($this->TypeName(), $this->JsonFormat());
    }

    protected function JsonFormattedValueList(): nodes\json\JsonFormattedValueList
    {
        $list = new nodes\json\JsonFormattedValueList([$this->JsonFormattedValue()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $list[] = $this->JsonFormattedValue();
        }

        return $list;
    }

    protected function JsonKeyValueList(): nodes\json\JsonKeyValueList
    {
        $list = new nodes\json\JsonKeyValueList([$this->JsonKeyValue()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $list[] = $this->JsonKeyValue();
        }

        return $list;
    }

    protected function JsonArgumentList(): nodes\json\JsonArgumentList
    {
        $list = new nodes\json\JsonArgumentList([$this->JsonArgument()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $list[] = $this->JsonArgument();
        }

        return $list;
    }

    /**
     * Parses JSON aggregate functions (json_arrayagg / json_objectagg)
     *
     * NB: for some strange reason these functions explicitly appear in func_expr_windowless production,
     * which is used for e.g. functions in FROM and for index definitions. Of course, using aggregate
     * functions in FROM causes an error.
     */
    protected function JsonAggregateFunc(bool $windowless = false): ?nodes\json\JsonAggregate
    {
        if (null === $keyword = $this->stream->matchesAnyKeyword(Keyword::JSON_ARRAYAGG, Keyword::JSON_OBJECTAGG)) {
            return null;
        }

        $this->stream->next();
        $this->stream->expect(TokenType::SPECIAL_CHAR, '(');
        if (Keyword::JSON_ARRAYAGG === $keyword) {
            $expression = new nodes\json\JsonArrayAgg($this->JsonFormattedValue());
            if ($this->stream->matchesKeywordSequence(Keyword::ORDER, Keyword::BY)) {
                $this->stream->skip(2);
                $expression->order = $this->OrderByList();
            }
            $expression->absentOnNull = $this->JsonNullClause();
        } else {
            $expression = new nodes\json\JsonObjectAgg(
                $this->JsonKeyValue(),
                $this->JsonNullClause(),
                $this->JsonUniquenessConstraint()
            );
        }
        $expression->returning = $this->JsonReturningClause();
        $this->stream->expect(TokenType::SPECIAL_CHAR, ')');

        if (!$windowless) {
            $expression->filter = $this->FilterClause();
            $expression->over   = $this->OverClause();
        }

        return $expression;
    }

    protected function JsonArrayConstructor(): nodes\json\JsonArray
    {
        if (self::PARENTHESES_SELECT === $this->checkContentsOfParentheses(-1)) {
            return new nodes\json\JsonArraySubselect(
                $this->SelectStatement(),
                $this->JsonFormat(),
                $this->JsonReturningClause()
            );
        } elseif (
            Keyword::RETURNING === $this->stream->getKeyword()
            || $this->stream->matchesSpecialChar(')')
        ) {
            return new nodes\json\JsonArrayValueList(null, null, $this->JsonReturningClause());
        }

        return new nodes\json\JsonArrayValueList(
            $this->JsonFormattedValueList(),
            $this->JsonNullClause(),
            $this->JsonReturningClause()
        );
    }

    protected function JsonObjectConstructor(): nodes\FunctionLike
    {
        if ($this->stream->matchesSpecialChar(')')) {
            return new nodes\json\JsonObject();
        } elseif (Keyword::RETURNING === $this->stream->getKeyword()) {
            return new nodes\json\JsonObject(null, null, null, $this->JsonReturningClause());
        }

        // Now it's getting tricky: all other variants of json_object() should have at least one argument.
        // We need to differentiate between a list of function arguments and a list of key-value pairs
        if (
            $this->stream->look(1)->matches(TokenType::COLON_EQUALS)
            || $this->stream->look(1)->matches(TokenType::EQUALS_GREATER)
        ) {
            // If we are seeing this, we are currently at a named function argument
            return new nodes\expressions\FunctionExpression(
                new nodes\QualifiedName('json_object'),
                $this->FunctionArgumentList()
            );
        }

        // This can be either a function argument or a key for JSON key-value pair
        $argument = $this->Expression();
        if ($this->stream->matchesSpecialChar([',', ')'])) {
            $arguments = new nodes\lists\FunctionArgumentList([$argument]);
            if ($this->stream->matchesSpecialChar(',')) {
                $this->stream->next();
                $arguments->merge($this->FunctionArgumentList());
            }
            return new nodes\expressions\FunctionExpression(new nodes\QualifiedName('json_object'), $arguments);
        } else {
            if (Keyword::VALUE !== $this->stream->getKeyword()) {
                $this->stream->expect(TokenType::SPECIAL_CHAR, ':');
            } else {
                $this->stream->next();
            }
            $arguments = new nodes\json\JsonKeyValueList([
                new nodes\json\JsonKeyValue($argument, $this->JsonFormattedValue())
            ]);
            if ($this->stream->matchesSpecialChar(',')) {
                $this->stream->next();
                $arguments->merge($this->JsonKeyValueList());
            }
            return new nodes\json\JsonObject(
                $arguments,
                $this->JsonNullClause(),
                $this->JsonUniquenessConstraint(),
                $this->JsonReturningClause()
            );
        }
    }


    protected function JsonQueryFunction(string $funcName): nodes\json\JsonQueryCommon
    {
        $context = $this->JsonFormattedValue();
        $this->stream->expect(TokenType::SPECIAL_CHAR, ',');
        $path    = $this->Expression();
        if (Keyword::PASSING !== $this->stream->getKeyword()) {
            $passing = null;
        } else {
            $this->stream->next();
            $passing = $this->JsonArgumentList();
        }

        switch ($funcName) {
            case 'json_exists':
                [, $onError] = $this->JsonBehaviour(nodes\json\JsonExists::class);
                return new nodes\json\JsonExists($context, $path, $passing, $onError);

            case 'json_value':
                $returning = $this->JsonReturningClause();
                [$onEmpty, $onError] = $this->JsonBehaviour(nodes\json\JsonValue::class);
                return new nodes\json\JsonValue($context, $path, $passing, $returning, $onEmpty, $onError);

            case 'json_query':
                $returning  = $this->JsonReturningClause();
                $wrapper    = $this->JsonWrapperClause();
                $keepQuotes = $this->JsonQuotesClause();
                [$onEmpty, $onError] = $this->JsonBehaviour(nodes\json\JsonQuery::class);
                return new nodes\json\JsonQuery(
                    $context,
                    $path,
                    $passing,
                    $returning,
                    $wrapper,
                    $keepQuotes,
                    $onEmpty,
                    $onError
                );

            default:
                throw new exceptions\InvalidArgumentException("Unknown JSON query function name $funcName");
        }
    }

    protected function JsonWrapperClause(): ?enums\JsonWrapper
    {
        if (null === $wrapper = $this->stream->matchesAnyKeyword(Keyword::WITH, Keyword::WITHOUT)) {
            return null;
        }

        $this->stream->next();
        if (Keyword::WITHOUT === $wrapper) {
            $result = enums\JsonWrapper::WITHOUT;
        } else {
            if (null === $check = $this->stream->matchesAnyKeyword(Keyword::CONDITIONAL, Keyword::UNCONDITIONAL)) {
                $result = enums\JsonWrapper::UNCONDITIONAL;
            } else {
                $this->stream->next();
                $result = enums\JsonWrapper::fromKeywords($wrapper, $check);
            }
        }
        if (Keyword::ARRAY === $this->stream->getKeyword()) {
            $this->stream->next();
        }
        $this->stream->expectKeyword(Keyword::WRAPPER);

        return $result;
    }

    protected function JsonQuotesClause(): ?bool
    {
        if (null === $keyword = $this->stream->matchesAnyKeyword(Keyword::KEEP, Keyword::OMIT)) {
            return null;
        }

        $this->stream->next();
        $keepQuotes = Keyword::KEEP === $keyword;
        $this->stream->expectKeyword(Keyword::QUOTES);
        if (Keyword::ON === $this->stream->getKeyword()) {
            $this->stream->next();
            $this->stream->expectKeyword(Keyword::SCALAR);
            $this->stream->expectKeyword(Keyword::STRING);
        }

        return $keepQuotes;
    }

    /**
     * @param class-string<Node> $className
     */
    protected function JsonBehaviour(string $className): array
    {
        $onError   = null;
        $onEmpty   = null;
        while (
            null !== $keyword = $this->stream->matchesAnyKeyword(
                Keyword::NULL,
                Keyword::ERROR,
                Keyword::TRUE,
                Keyword::FALSE,
                Keyword::UNKNOWN,
                Keyword::EMPTY,
                Keyword::DEFAULT
            )
        ) {
            $keywords       = [$keyword];
            $behaviourToken = $this->stream->next();
            $behaviour      = null;
            if (Keyword::DEFAULT === $keyword) {
                $behaviour = $this->Expression();
            } elseif (Keyword::EMPTY === $keyword) {
                if (null !== $empty = $this->stream->matchesAnyKeyword(Keyword::ARRAY, Keyword::OBJECT)) {
                    $this->stream->next();
                    $keywords[] = $empty;
                } else {
                    $keywords[] = Keyword::ARRAY;
                }
            }
            $behaviour ??= enums\JsonBehaviour::fromKeywords(...$keywords);

            $onToken = $this->stream->expect(TokenType::KEYWORD, 'on');
            $onWhat  = $this->stream->expectKeyword(Keyword::EMPTY, Keyword::ERROR);
            if (Keyword::ERROR === $onWhat && null === $onError) {
                $onError = $behaviour;
            } elseif (Keyword::EMPTY === $onWhat && null === $onEmpty && null === $onError) {
                $onEmpty = $behaviour;
            } else {
                // Error should point to the first relevant token rather than to the current one
                throw exceptions\SyntaxException::expectationFailed(
                    TokenType::SPECIAL_CHAR,
                    ')',
                    $behaviourToken,
                    $this->stream->getSource()
                );
            }

            $checkCase  = $behaviour instanceof enums\JsonBehaviour ? $behaviour : enums\JsonBehaviour::DEFAULT;
            $applicable = Keyword::EMPTY === $onWhat
                ? enums\JsonBehaviour::casesForOnEmptyClause($className)
                : enums\JsonBehaviour::casesForOnErrorClause($className);

            // If no applicable cases found, point error at "ON" token
            if ([] === $applicable) {
                throw exceptions\SyntaxException::atPosition(
                    \sprintf("Unexpected %s clause", Keyword::EMPTY === $onWhat ? 'ON EMPTY' : 'ON ERROR'),
                    $this->stream->getSource(),
                    $onToken->getPosition()
                );
            }
            // Invalid case: point error to behaviour token and give valid behaviours, as Postgres does
            if (!\in_array($checkCase, $applicable, true)) {
                throw exceptions\SyntaxException::atPosition(
                    \sprintf(
                        "Unexpected %s, expecting one of %s",
                        $checkCase->nameForExceptionMessage(),
                        \implode(', ', \array_map(
                            fn (enums\JsonBehaviour $behaviour): string => $behaviour->nameForExceptionMessage(),
                            $applicable
                        ))
                    ),
                    $this->stream->getSource(),
                    $behaviourToken->getPosition()
                );
            }
        }

        return [$onEmpty, $onError];
    }

    protected function JsonTable(): nodes\range\JsonTable
    {
        $this->stream->expectKeyword(Keyword::JSON_TABLE);
        $this->stream->expect(TokenType::SPECIAL_CHAR, '(');

        $context = $this->JsonFormattedValue();
        $this->stream->expect(TokenType::SPECIAL_CHAR, ',');
        $path    = $this->Expression();
        if (Keyword::AS !== $this->stream->getKeyword()) {
            $pathName = null;
        } else {
            $this->stream->next();
            $pathName = $this->ColId();
        }
        if (Keyword::PASSING !== $this->stream->getKeyword()) {
            $passing = null;
        } else {
            $this->stream->next();
            $passing = $this->JsonArgumentList();
        }
        $columns = $this->JsonTableColumnsClause();
        [, $onError] = $this->JsonBehaviour(nodes\range\JsonTable::class);

        $table = new nodes\range\JsonTable(
            $context,
            $path,
            $pathName,
            $passing,
            $columns,
            $onError
        );

        $this->stream->expect(TokenType::SPECIAL_CHAR, ')');
        if ($alias = $this->OptionalAliasClause()) {
            $table->setAlias($alias[0], $alias[1]);
        }

        return $table;
    }

    protected function JsonTableColumnsClause(): nodes\range\json\JsonColumnDefinitionList
    {
        $this->stream->expectKeyword(Keyword::COLUMNS);
        $this->stream->expect(TokenType::SPECIAL_CHAR, '(');

        $list = $this->JsonColumnDefinitionList();

        $this->stream->expect(TokenType::SPECIAL_CHAR, ')');

        return $list;
    }

    protected function JsonColumnDefinitionList(): nodes\range\json\JsonColumnDefinitionList
    {
        $list = new nodes\range\json\JsonColumnDefinitionList([$this->JsonColumnDefinition()]);

        while ($this->stream->matches(TokenType::SPECIAL_CHAR, ',')) {
            $this->stream->next();
            $list[] = $this->JsonColumnDefinition();
        }

        return $list;
    }

    protected function JsonColumnDefinition(): nodes\range\json\JsonColumnDefinition
    {
        if (Keyword::NESTED === $this->stream->getKeyword()) {
            return $this->JsonNestedColumns();
        }

        $name = $this->ColId();
        if ($this->stream->matchesKeywordSequence(Keyword::FOR, Keyword::ORDINALITY)) {
            $this->stream->skip(2);
            return new nodes\range\json\JsonOrdinalityColumnDefinition($name);
        }

        $type = $this->TypeName();
        if (Keyword::EXISTS === $this->stream->getKeyword()) {
            $this->stream->skip(1);
            $path        = $this->JsonConstantPath();
            [, $onError] = $this->JsonBehaviour(nodes\range\json\JsonExistsColumnDefinition::class);
            return new nodes\range\json\JsonExistsColumnDefinition($name, $type, $path, $onError);
        }

        $format     = Keyword::FORMAT === $this->stream->getKeyword() ? $this->JsonFormat() : null;
        $path       = $this->JsonConstantPath();
        $wrapper    = $this->JsonWrapperClause();
        $keepQuotes = $this->JsonQuotesClause();
        [$onEmpty, $onError] = $this->JsonBehaviour(nodes\range\json\JsonRegularColumnDefinition::class);
        return new nodes\range\json\JsonRegularColumnDefinition(
            $name,
            $type,
            $format,
            $path,
            $wrapper,
            $keepQuotes,
            $onEmpty,
            $onError
        );
    }

    protected function JsonConstantPath(): ?nodes\expressions\StringConstant
    {
        if (Keyword::PATH !== $this->stream->getKeyword()) {
            return null;
        }

        $this->stream->skip(1);
        return new nodes\expressions\StringConstant($this->stream->expect(TokenType::STRING)->getValue());
    }

    protected function JsonNestedColumns(): nodes\range\json\JsonNestedColumns
    {
        $this->stream->expectKeyword(Keyword::NESTED);
        if (Keyword::PATH === $this->stream->getKeyword()) {
            $this->stream->next();
        }
        $path = new nodes\expressions\StringConstant($this->stream->expect(TokenType::STRING)->getValue());
        if (Keyword::AS !== $this->stream->getKeyword()) {
            $pathName = null;
        } else {
            $this->stream->next();
            $pathName = $this->ColId();
        }

        return new nodes\range\json\JsonNestedColumns($path, $pathName, $this->JsonTableColumnsClause());
    }
}
