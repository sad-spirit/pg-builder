<?php

/**
 * Query builder for PostgreSQL backed by a query parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder;

use Psr\Cache\CacheItemPoolInterface;

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
 * @method nodes\OnConflictClause           parseOnConflict($input)
 * @method nodes\IndexParameters            parseIndexParameters($input)
 * @method nodes\IndexElement               parseIndexElement($input)
 * @method nodes\lists\GroupByList          parseGroupByList($input)
 * @method nodes\ScalarExpression|nodes\group\GroupByElement parseGroupByElement($input)
 * @method nodes\xml\XmlNamespaceList       parseXmlNamespaceList($input)
 * @method nodes\xml\XmlNamespace           parseXmlNamespace($input)
 * @method nodes\xml\XmlColumnList          parseXmlColumnList($input)
 * @method nodes\xml\XmlColumnDefinition    parseXmlColumnDefinition($input)
 * @method nodes\TypeName                   parseTypeName($input)
 */
class Parser
{
    /**
     * mathOp production from grammar
     * @var array
     */
    protected static $mathOp = ['+', '-', '*', '/', '%', '^', '<', '>', '=', '<=', '>=', '!=', '<>'];

    /**
     * sub_type production from grammar
     * @var array
     */
    protected static $subType = ['any', 'all', 'some'];

    /**
     * Methods that are exposed through __call()
     * @var array
     */
    protected static $callable = [
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
        'indexparameters'            => true,
        'indexelement'               => true,
        'onconflict'                 => true,
        'groupbylist'                => true,
        'groupbyelement'             => true,
        'xmlnamespacelist'           => true,
        'xmlnamespace'               => true,
        'xmlcolumnlist'              => true,
        'xmlcolumndefinition'        => true,
        'typename'                   => true
    ];

    /**
     * @var Lexer
     */
    private $lexer;

    /**
     * @var CacheItemPoolInterface
     */
    private $cache;

    /**
     * @var TokenStream
     */
    protected $stream;

    /**
     * Cache for various match...() calls
     *
     * @var array
     */
    private $matched;

    /**
     * Guesses the type of parenthesised expression
     *
     * Parentheses may contain
     *  * expressions: (foo + bar)
     *  * row constructors: (foo, bar)
     *  * subselects (select foo, bar)
     *
     * @return null|string Either of 'select', 'row' or 'expression'. Null if stream was not on opening parenthesis
     * @throws exceptions\SyntaxException in case of unclosed parenthesis
     */
    private function checkContentsOfParentheses()
    {
        $openParens = [];
        $lookIdx    = 0;
        while ($this->stream->look($lookIdx)->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
            $openParens[] = $lookIdx++;
        }
        if (!$lookIdx) {
            return null;
        }

        if (!$this->stream->look($lookIdx)->matches(Token::TYPE_KEYWORD, ['values', 'select', 'with'])) {
            $selectLevel = false;
        } elseif (1 === ($selectLevel = count($openParens))) {
            return 'select';
        }

        do {
            $token = $this->stream->look(++$lookIdx);
            if (!$token->matches(Token::TYPE_SPECIAL_CHAR)) {
                continue;
            }
            switch ($token->getValue()) {
                case '[':
                    $lookIdx = $this->skipParentheses($lookIdx, true) - 1;
                    break;

                case '(':
                    $openParens[] = $lookIdx;
                    break;

                case ',':
                    if (1 === count($openParens) && !$selectLevel) {
                        return 'row';
                    }
                    break;

                case ')':
                    if (1 < count($openParens) && $selectLevel === count($openParens)) {
                        if (
                            $this->stream->look($lookIdx + 1)->matches(
                                Token::TYPE_KEYWORD,
                                ['union', 'intersect', 'except', 'order', 'limit', 'offset',
                                    'for' /* ...update */, 'fetch' /* SQL:2008 limit */]
                            )
                            || $this->stream->look($lookIdx + 1)->matches(Token::TYPE_SPECIAL_CHAR, ')')
                        ) {
                            // this addresses stuff like ((select 1) order by 1)
                            $selectLevel--;
                        } else {
                            $selectLevel = false;
                        }
                    }
                    array_pop($openParens);
            }
        } while (!empty($openParens) && !$token->matches(Token::TYPE_EOF));

        if (!empty($openParens)) {
            $token = $this->stream->look(array_shift($openParens));
            throw exceptions\SyntaxException::atPosition(
                "Unbalanced '('",
                $this->stream->getSource(),
                $token->getPosition()
            );
        }

        return $selectLevel ? 'select' : 'expression';
    }

    /**
     * Skips the expression enclosed in parentheses
     *
     * @param int  $start  Starting lookahed position in token stream
     * @param bool $square Whether we are skipping square brackets [] rather than ()
     * @return int Position after the closing ']' or ')'
     * @throws exceptions\SyntaxException in case of unclosed parentheses
     */
    private function skipParentheses($start, $square = false)
    {
        $lookIdx    = $start;
        $openParens = 1;

        do {
            $token = $this->stream->look(++$lookIdx);
            switch ($token->getType()) {
                case Token::TYPE_SPECIAL_CHAR:
                    if ($token->getValue() === ($square ? '[' : '(')) {
                        $openParens++;
                    } elseif ($token->getValue() === ($square ? ']' : ')')) {
                        $openParens--;
                    }
                    break;
                case Token::TYPE_EOF:
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
     *
     * @return bool
     */
    private function matchesOperator()
    {
        return $this->stream->matches(Token::TYPE_OPERATOR)
               || $this->stream->matchesKeyword('operator')
                  && $this->stream->look(1)->matches(Token::TYPE_SPECIAL_CHAR, '(');
    }


    /**
     * Tests whether current position of stream matches 'func_name' production from PostgreSQL's grammar
     *
     * Actually func_name allows indirection via array subscripts and appearance of '*' in
     * name, these are only disallowed later in processing, we disallow these here.
     *
     * @return int|false position after func_name if matches, false if not
     */
    private function matchesFuncName()
    {
        $key = 'FuncName:' . $this->stream->getCurrent()->getPosition();

        if (!array_key_exists($key, $this->matched)) {
            if (
                !$this->stream->matches(Token::TYPE_IDENTIFIER)
                && (!$this->stream->matches(Token::TYPE_KEYWORD)
                    || $this->stream->matches(Token::TYPE_RESERVED_KEYWORD))
            ) {
                $this->matched[$key] = false;
            } else {
                $first = $this->stream->getCurrent();
                $idx   = 0;
                while (
                    $this->stream->look($idx + 1)->matches(Token::TYPE_SPECIAL_CHAR, '.')
                    && ($this->stream->look($idx + 2)->matches(Token::TYPE_IDENTIFIER)
                        || $this->stream->look($idx + 2)->matches(Token::TYPE_KEYWORD))
                ) {
                    $idx += 2;
                }
                if (
                    Token::TYPE_TYPE_FUNC_NAME_KEYWORD === $first->getType() && 1 < $idx
                    || Token::TYPE_COL_NAME_KEYWORD === $first->getType() && 1 === $idx
                ) {
                    // does not match func_name production
                    $this->matched[$key] = false;
                } else {
                    $this->matched[$key] = $idx + 1;
                }
            }
        }

        return $this->matched[$key];
    }

    /**
     * Tests whether current position of stream matches a function call
     *
     * @return bool
     */
    private function matchesFunctionCall()
    {
        static $noParens = [
            'current_date', 'current_time', 'current_timestamp', 'localtime', 'localtimestamp',
            'current_role', 'current_user', 'session_user', 'user', 'current_catalog', 'current_schema'
        ];
        static $parens = [
            'cast', 'extract', 'overlay', 'position', 'substring', 'treat', 'trim', 'nullif', 'coalesce',
            'greatest', 'least', 'xmlconcat', 'xmlelement', 'xmlexists', 'xmlforest', 'xmlparse',
            'xmlpi', 'xmlroot', 'xmlserialize', 'normalize'
        ];

        $key = 'FunctionCall:' . $this->stream->getCurrent()->getPosition();

        if (!array_key_exists($key, $this->matched)) {
            if (
                $this->stream->matchesKeyword($noParens) // function-like stuff that doesn't need parentheses
                || ($this->stream->matchesKeyword($parens) // known functions that require parentheses
                    && $this->stream->look(1)->matches(Token::TYPE_SPECIAL_CHAR, '('))
                || ($this->stream->matchesKeywordSequence('collation', 'for') // COLLATION FOR (...)
                    && $this->stream->look(2)->matches(Token::TYPE_SPECIAL_CHAR, '('))
            ) {
                $this->matched[$key] = true;
            } else {
                // generic function name
                $this->matched[$key] = false !== ($idx = $this->matchesFuncName())
                                       && $this->stream->look($idx)->matches(Token::TYPE_SPECIAL_CHAR, '(');
            }
        }

        return $this->matched[$key];
    }

    /**
     * Tests whether current position of stream looks like a start of Expression()
     *
     * Used to decide whether a custom operator should be infix or postfix one, the
     * former having higher precedence.
     *
     * @return bool
     */
    private function matchesExpressionStart()
    {
        return (!$this->stream->matches(Token::TYPE_RESERVED_KEYWORD)
                && !$this->stream->matches(Token::TYPE_SPECIAL))
               || $this->stream->matchesKeyword(['not', 'true', 'false', 'null', 'row', 'array', 'case', 'exists'])
               || $this->stream->matchesSpecialChar(['(', '+', '-'])
               || $this->stream->matches(Token::TYPE_OPERATOR)
               || $this->matchesFunctionCall();
    }

    /**
     * Tests whether current position of stream looks like a type cast with standard type name
     *
     * i.e. "typename 'string constant'" where typename is SQL standard one: "integer" but not "int4"
     *
     * @return bool
     */
    private function matchesConstTypecast()
    {
        static $constNames = [
            'int', 'integer', 'smallint', 'bigint', 'real', 'float', 'decimal', 'dec', 'numeric', 'boolean',
            'double', 'bit', 'national', 'character', 'char', 'varchar', 'nchar', 'time', 'timestamp', 'interval'
        ];
        static $requiredSequence = [
            'double'   => 'precision',
            'national' => ['character', 'char']
        ];
        static $optVarying = [
            'bit', 'character', 'char', 'nchar', 'national'
        ];
        static $noModifiers = [
            'int', 'integer', 'smallint', 'bigint', 'real', 'boolean', 'double'
        ];
        static $trailingTimezone = [
            'time', 'timestamp'
        ];

        if (!$this->stream->matchesKeyword($constNames)) {
            return false;
        }
        $base = $this->stream->getCurrent()->getValue();
        $idx  = 1;
        if (
            isset($requiredSequence[$base])
            && !$this->stream->look($idx++)->matches(Token::TYPE_KEYWORD, $requiredSequence[$base])
        ) {
            return false;
        }
        if (in_array($base, $optVarying) && $this->stream->look($idx)->matches(Token::TYPE_KEYWORD, 'varying')) {
            $idx++;
        }
        if (
            !in_array($base, $noModifiers)
            && $this->stream->look($idx)->matches(Token::TYPE_SPECIAL_CHAR, '(')
        ) {
            $idx = $this->skipParentheses($idx);
        }
        if (in_array($base, $trailingTimezone)) {
            if ($this->stream->look($idx)->matches(Token::TYPE_KEYWORD, ['with', 'without'])) {
                $idx += 3;
            }
        }

        return $this->stream->look($idx)->matches(Token::TYPE_STRING);
    }

    /**
     * Tests whether current position of stream looks like a constant with a type cast: typename 'string constant'
     *
     * @return bool
     */
    private function matchesTypecast()
    {
        if ($this->matchesConstTypecast()) {
            return true;

        } elseif (false === ($idx = $this->matchesFuncName())) {
            return false;
        }

        if (
            $this->stream->look($idx)->matches(Token::TYPE_SPECIAL_CHAR, '(')
            && !$this->stream->look($idx + 1)->matches(Token::TYPE_SPECIAL_CHAR, ')')
        ) {
            $idx = $this->skipParentheses($idx);
        }

        return $this->stream->look($idx)->matches(Token::TYPE_STRING);
    }

    /**
     * Constructor, sets Lexer and Cache implementations to use
     *
     * It is recommended to always use cache in production: parsing is slow.
     *
     * @param Lexer                  $lexer
     * @param CacheItemPoolInterface $cache
     */
    public function __construct(Lexer $lexer, CacheItemPoolInterface $cache = null)
    {
        $this->lexer = $lexer;
        $this->cache = $cache;
    }

    /**
     * Magic method for function overloading
     *
     * The method allows calling parseWhatever() methods that map to protected Whatever() methods
     * listed in $callable static property.
     *
     * @param string $name
     * @param array  $arguments
     * @return mixed
     * @throws exceptions\BadMethodCallException
     * @throws exceptions\SyntaxException
     */
    public function __call($name, array $arguments)
    {
        if (
            !preg_match('/^parse([a-zA-Z]+)$/', $name, $matches)
            || !isset(self::$callable[strtolower($matches[1])])
        ) {
            throw new exceptions\BadMethodCallException("The method '{$name}' is not available");
        }

        if (!$this->cache) {
            $cacheItem = null;

        } else {
            $cacheKey  = 'parsetree-' . md5('{' . $name . '}' . $arguments[0]);
            $cacheItem = $this->cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                return clone $cacheItem->get();
            }
        }

        if ($arguments[0] instanceof TokenStream) {
            $this->stream = $arguments[0];
        } else {
            $this->stream = $this->lexer->tokenize($arguments[0]);
        }

        $this->matched = [];

        $parsed = call_user_func([$this, $matches[1]]);

        if (!$this->stream->isEOF()) {
            throw exceptions\SyntaxException::expectationFailed(
                Token::TYPE_EOF,
                null,
                $this->stream->getCurrent(),
                $this->stream->getSource()
            );
        }

        if ($cacheItem) {
            $this->cache->save($cacheItem->set(clone $parsed));
        }

        return $parsed;
    }

    protected function Statement()
    {
        if ($this->stream->matchesKeyword('with')) {
            $withClause = $this->WithClause();
        }

        if (
            $this->stream->matchesKeyword(['select', 'values'])
            || $this->stream->matchesSpecialChar('(')
        ) {
            $stmt = $this->SelectStatement();
            if (!empty($withClause)) {
                if (0 < count($stmt->with)) {
                    throw new exceptions\SyntaxException('Multiple WITH clauses are not allowed');
                }
                $stmt->with = $withClause;
            }
            return $stmt;

        } elseif ($this->stream->matchesKeyword('insert')) {
            $stmt = $this->InsertStatement();

        } elseif ($this->stream->matchesKeyword('update')) {
            $stmt = $this->UpdateStatement();

        } elseif ($this->stream->matchesKeyword('delete')) {
            $stmt = $this->DeleteStatement();

        } else {
            throw new exceptions\SyntaxException(
                'Unexpected ' . $this->stream->getCurrent()
                . ', expecting SELECT / INSERT / UPDATE / DELETE statement'
            );
        }

        if (!empty($withClause)) {
            $stmt->with = $withClause;
        }
        return $stmt;
    }

    protected function SelectStatement()
    {
        if ($this->stream->matchesKeyword('with')) {
            $withClause = $this->WithClause();
        }

        $stmt = $this->SelectIntersect();

        while ($this->stream->matchesKeyword(['union', 'except'])) {
            $setOp = $this->stream->next()->getValue();
            if ($this->stream->matchesKeyword(['all', 'distinct'])) {
                $setOp .= ('all' === $this->stream->next()->getValue() ? ' all' : '');
            }
            $stmt = new SetOpSelect($stmt, $this->SelectIntersect(), $setOp);
        }

        if (!empty($withClause)) {
            if ($stmt->with) {
                throw new exceptions\SyntaxException(
                    'Multiple WITH clauses are not allowed'
                );
            }
            $stmt->with = $withClause;
        }

        // Per SQL spec ORDER BY and later clauses apply to a result of set operation,
        // not to a single participating SELECT
        if ($this->stream->matchesKeywordSequence('order', 'by')) {
            if (count($stmt->order) > 0) {
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
        if ($this->stream->matchesKeyword(['for', 'limit', 'offset', 'fetch'])) {
            if ('for' === $this->stream->getCurrent()->getValue()) {
                // locking clause first
                $this->ForLockingClause($stmt);
                if ($this->stream->matchesKeyword(['limit', 'offset', 'fetch'])) {
                    $this->LimitOffsetClause($stmt);
                }

            } else {
                // limit clause first
                $this->LimitOffsetClause($stmt);
                if ($this->stream->matchesKeyword('for')) {
                    $this->ForLockingClause($stmt);
                }
            }
        }

        return $stmt;
    }

    protected function InsertStatement()
    {
        if ($this->stream->matchesKeyword('with')) {
            $withClause = $this->WithClause();
        }
        $this->stream->expect(Token::TYPE_KEYWORD, 'insert');
        $this->stream->expect(Token::TYPE_KEYWORD, 'into');

        $stmt = new Insert($this->InsertTarget());
        if (!empty($withClause)) {
            $stmt->with = $withClause;
        }

        if ($this->stream->matchesKeywordSequence('default', 'values')) {
            $this->stream->skip(2);
        } else {
            if (
                $this->stream->matchesSpecialChar('(')
                && 'select' !== $this->checkContentsOfParentheses()
            ) {
                $this->stream->next();
                $stmt->cols->replace($this->InsertTargetList());
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            }
            if ($this->stream->matchesKeyword('overriding')) {
                $this->stream->next();
                $stmt->setOverriding(
                    $this->stream->expect(Token::TYPE_KEYWORD, ['user', 'system'])->getValue()
                );
                $this->stream->expect(Token::TYPE_KEYWORD, 'value');
            }
            $stmt->values = $this->SelectStatement();
        }

        if ($this->stream->matchesKeywordSequence('on', 'conflict')) {
            $this->stream->skip(2);
            $stmt->onConflict = $this->OnConflict();
        }

        if ($this->stream->matchesKeyword('returning')) {
            $this->stream->next();
            $stmt->returning->replace($this->TargetList());
        }

        return $stmt;
    }

    /**
     * @return Update
     * @throws exceptions\NotImplementedException
     */
    protected function UpdateStatement()
    {
        if ($this->stream->matchesKeyword('with')) {
            $withClause = $this->WithClause();
        }

        $this->stream->expect(Token::TYPE_KEYWORD, 'update');
        $relation = $this->RelationExpression('update');
        $this->stream->expect(Token::TYPE_KEYWORD, 'set');

        $stmt = new Update($relation, $this->SetClauseList());

        if (!empty($withClause)) {
            $stmt->with = $withClause;
        }

        if ($this->stream->matchesKeyword('from')) {
            $this->stream->next();
            $stmt->from->replace($this->FromList());
        }
        if ($this->stream->matchesKeyword('where')) {
            $this->stream->next();
            if ($this->stream->matchesKeywordSequence('current', 'of')) {
                throw new exceptions\NotImplementedException('WHERE CURRENT OF clause is not supported');
            }
            $stmt->where->condition = $this->Expression();
        }
        if ($this->stream->matchesKeyword('returning')) {
            $this->stream->next();
            $stmt->returning->replace($this->TargetList());
        }

        return $stmt;
    }

    /**
     * @return Delete
     * @throws exceptions\NotImplementedException
     */
    protected function DeleteStatement()
    {
        if ($this->stream->matchesKeyword('with')) {
            $withClause = $this->WithClause();
        }
        $this->stream->expect(Token::TYPE_KEYWORD, 'delete');
        $this->stream->expect(Token::TYPE_KEYWORD, 'from');

        $stmt = new Delete($this->RelationExpression('delete'));

        if (!empty($withClause)) {
            $stmt->with = $withClause;
        }

        if ($this->stream->matchesKeyword('using')) {
            $this->stream->next();
            $stmt->using->replace($this->FromList());
        }
        if ($this->stream->matchesKeyword('where')) {
            $this->stream->next();
            if ($this->stream->matchesKeywordSequence('current', 'of')) {
                throw new exceptions\NotImplementedException('WHERE CURRENT OF clause is not supported');
            }
            $stmt->where->condition = $this->Expression();
        }
        if ($this->stream->matchesKeyword('returning')) {
            $this->stream->next();
            $stmt->returning->replace($this->TargetList());
        }

        return $stmt;
    }

    protected function WithClause()
    {
        $this->stream->expect(Token::TYPE_KEYWORD, 'with');
        if ($recursive = $this->stream->matchesKeyword('recursive')) {
            $this->stream->next();
        }

        $ctes = [$this->CommonTableExpression()];
        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $ctes[] = $this->CommonTableExpression();
        }

        return new nodes\WithClause($ctes, $recursive);
    }

    protected function CommonTableExpression()
    {
        $alias         = $this->ColId();
        $columnAliases = new nodes\lists\IdentifierList();
        $materialized  = null;
        if ($this->stream->matchesSpecialChar('(')) {
            do {
                $this->stream->next();
                $columnAliases[] = $this->ColId();
            } while ($this->stream->matchesSpecialChar(','));
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
        }
        $this->stream->expect(Token::TYPE_KEYWORD, 'as');

        if ($this->stream->matchesKeyword('materialized')) {
            $materialized = true;
            $this->stream->next();
        } elseif ($this->stream->matchesKeyword('not')) {
            $materialized = false;
            $this->stream->next();
            $this->stream->expect(Token::TYPE_KEYWORD, 'materialized');
        }

        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
        $statement = $this->Statement();
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        return new nodes\CommonTableExpression($statement, $alias, $columnAliases, $materialized);
    }

    protected function ForLockingClause(SelectCommon $stmt)
    {
        if ($this->stream->matchesKeywordSequence('for', 'read', 'only')) {
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

    protected function LockingList()
    {
        $list = new nodes\lists\LockList();

        do {
            $list[] = $this->LockingElement();
        } while ($this->stream->matchesKeyword('for'));

        return $list;
    }

    protected function LockingElement()
    {
        $this->stream->expect(Token::TYPE_KEYWORD, 'for');
        switch (
            $strength = $this->stream->expect(Token::TYPE_KEYWORD, ['update', 'no', 'share', 'key'])
                    ->getValue()
        ) {
            case 'no':
                $strength .= ' ' . $this->stream->expect(Token::TYPE_KEYWORD, 'key')->getValue()
                         . ' ' . $this->stream->expect(Token::TYPE_KEYWORD, 'update')->getValue();
                break;
            case 'key':
                $strength .= ' ' . $this->stream->expect(Token::TYPE_KEYWORD, 'share')->getValue();
        }

        $relations  = [];
        $noWait     = false;
        $skipLocked = false;

        if ($this->stream->matchesKeyword('of')) {
            do {
                $this->stream->next();
                $relations[] = $this->QualifiedName();
            } while ($this->stream->matchesSpecialChar(','));
        }

        if ($this->stream->matchesKeyword('nowait')) {
            $this->stream->next();
            $noWait = true;

        } elseif ($this->stream->matchesKeywordSequence('skip', 'locked')) {
            $this->stream->skip(2);
            $skipLocked = true;
        }

        return new nodes\LockingElement($strength, $relations, $noWait, $skipLocked);
    }

    protected function LimitOffsetClause(SelectCommon $stmt)
    {
        // LIMIT and OFFSET clauses may come in any order
        if ($this->stream->matchesKeyword('offset')) {
            $this->OffsetClause($stmt);
            if ($this->stream->matchesKeyword(['limit', 'fetch'])) {
                $this->LimitClause($stmt);
            }
        } else {
            $this->LimitClause($stmt);
            if ($this->stream->matchesKeyword('offset')) {
                $this->OffsetClause($stmt);
            }
        }
    }

    protected function LimitClause(SelectCommon $stmt)
    {
        if ($stmt->limit) {
            throw exceptions\SyntaxException::atPosition(
                'Multiple LIMIT clauses are not allowed',
                $this->stream->getSource(),
                $this->stream->getCurrent()->getPosition()
            );
        }
        if ($this->stream->matchesKeyword('limit')) {
            // Traditional Postgres LIMIT clause
            $this->stream->next();
            if ($this->stream->matchesKeyword('all')) {
                $this->stream->next();
                $stmt->limit = new nodes\Constant(null);
            } else {
                $stmt->limit = $this->Expression();
            }

        } else {
            // SQL:2008 syntax
            $this->stream->expect(Token::TYPE_KEYWORD, 'fetch');
            $this->stream->expect(Token::TYPE_KEYWORD, ['first', 'next']);

            if ($this->stream->matchesKeyword(['row', 'rows'])) {
                // no limit specified -> 1 row
                $stmt->limit = new nodes\Constant(1);
            } elseif ($this->stream->matchesSpecialChar(['+', '-'])) {
                // signed numeric constant: that case is not handled by ExpressionAtom()
                $sign = $this->stream->next();
                if ($this->stream->matches(Token::TYPE_FLOAT)) {
                    $constantToken = $this->stream->next();
                } else {
                    $constantToken = $this->stream->expect(Token::TYPE_INTEGER);
                }
                if ('+' === $sign->getValue()) {
                    $stmt->limit = new nodes\Constant($constantToken);
                } else {
                    $stmt->limit = new nodes\Constant(new Token(
                        $constantToken->getType(),
                        '-' . $constantToken->getValue(),
                        $constantToken->getPosition()
                    ));
                }
            } else {
                $stmt->limit = $this->ExpressionAtom();
            }

            $this->stream->expect(Token::TYPE_KEYWORD, ['row', 'rows']);
            if ($this->stream->matchesKeywordSequence('with', 'ties')) {
                $stmt->limitWithTies = true;
                $this->stream->skip(2);
            } else {
                $this->stream->expect(Token::TYPE_KEYWORD, 'only');
            }
        }
    }

    protected function OffsetClause(SelectCommon $stmt)
    {
        if ($stmt->offset) {
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
        $this->stream->expect(Token::TYPE_KEYWORD, 'offset');
        $stmt->offset = $this->Expression();
        if ($this->stream->matchesKeyword(['row', 'rows'])) {
            $this->stream->next();
        }
    }

    protected function SetClauseList()
    {
        $targetList = new nodes\lists\SetClauseList([$this->SetClause()]);
        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $targetList[] = $this->SetClause();
        }
        return $targetList;
    }

    protected function SetClause()
    {
        if (!$this->stream->matchesSpecialChar('(')) {
            $column = $this->SetTargetElement();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '=');
            $value  = $this->ExpressionWithDefault();

            return new nodes\SingleSetClause($column, $value);

        } else {
            $this->stream->next();

            $columns = new nodes\lists\SetTargetList([$this->SetTargetElement()]);
            while ($this->stream->matchesSpecialChar(',')) {
                $this->stream->next();
                $columns[] = $this->SetTargetElement();
            }
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '=');

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

    protected function InsertTargetList()
    {
        $list = new nodes\lists\SetTargetList([$this->SetTargetElement()]);
        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $list[] = $this->SetTargetElement();
        }
        return $list;
    }

    protected function SetTargetElement()
    {
        return new nodes\SetTargetElement($this->ColId(), $this->Indirection(false));
    }

    protected function ExpressionListWithDefault()
    {
        $values = [$this->ExpressionWithDefault()];
        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $values[] = $this->ExpressionWithDefault();
        }

        return $values;
    }

    protected function ExpressionWithDefault()
    {
        if ($this->stream->matchesKeyword('default')) {
            $this->stream->next();
            return new nodes\SetToDefault();
        } else {
            return $this->Expression();
        }
    }

    protected function SelectWithParentheses()
    {
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
        $select = $this->SelectStatement();
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        return $select;
    }

    /**
     * SELECT ... [INTERSECT SELECT...]
     *
     */
    protected function SelectIntersect()
    {
        $stmt = $this->SimpleSelect();

        while ($this->stream->matchesKeyword('intersect')) {
            $setOp = $this->stream->next()->getValue();
            if ($this->stream->matchesKeyword(['all', 'distinct'])) {
                $setOp .= ('all' === $this->stream->next()->getValue() ? ' all' : '');
            }
            $stmt = new SetOpSelect($stmt, $this->SimpleSelect(), $setOp);
        }

        return $stmt;
    }

    protected function SimpleSelect()
    {
        if ($this->stream->matchesSpecialChar('(')) {
            return $this->SelectWithParentheses(); // select_with_parens grammar production
        }

        $token = $this->stream->expect(Token::TYPE_KEYWORD, ['select', 'values']);
        if ('values' === $token->getValue()) {
            return new Values($this->RowList());
        }

        $distinctClause = false;

        if ($this->stream->matchesKeyword('all')) {
            // noise "ALL"
            $this->stream->next();
        } elseif ($this->stream->matchesKeyword('distinct')) {
            $this->stream->next();
            if (!$this->stream->matchesKeyword('on')) {
                $distinctClause = true;
            } else {
                $this->stream->next();
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
                $distinctClause = $this->ExpressionList();
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            }
        }

        $stmt = new Select($this->TargetList(), $distinctClause);

        if ($this->stream->matchesKeyword('into')) {
            throw new exceptions\NotImplementedException("SELECT INTO clauses are not supported");
        }

        if ($this->stream->matchesKeyword('from')) {
            $this->stream->next();
            $stmt->from->replace($this->FromList());
        }

        if ($this->stream->matchesKeyword('where')) {
            $this->stream->next();
            $stmt->where->condition = $this->Expression();
        }

        if ($this->stream->matchesKeywordSequence('group', 'by')) {
            $this->stream->skip(2);
            $stmt->group->replace($this->GroupByList());
        }

        if ($this->stream->matchesKeyword('having')) {
            $this->stream->next();
            $stmt->having->condition = $this->Expression();
        }

        if ($this->stream->matchesKeyword('window')) {
            $this->stream->next();
            $stmt->window->replace($this->WindowList());
        }

        return $stmt;
    }

    protected function WindowList()
    {
        $windows = new nodes\lists\WindowList([$this->WindowDefinition()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $windows[] = $this->WindowDefinition();
        }

        return $windows;
    }

    protected function WindowDefinition()
    {
        $name    = $this->ColId();
        $this->stream->expect(Token::TYPE_KEYWORD, 'as');
        $spec    = $this->WindowSpecification();
        $spec->setName($name);

        return $spec;
    }

    protected function WindowSpecification()
    {
        $refName = $partition = $frame = $order = null;
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
        if (
            $this->stream->matchesAnyType(Token::TYPE_IDENTIFIER, Token::TYPE_COL_NAME_KEYWORD)
            || ($this->stream->matches(Token::TYPE_UNRESERVED_KEYWORD)
                // See comment for opt_existing_window_name production in gram.y
                && !in_array($this->stream->getCurrent()->getValue(), ['partition', 'range', 'rows', 'groups']))
        ) {
            $refName = $this->ColId();
        }
        if ($this->stream->matchesKeywordSequence('partition', 'by')) {
            $this->stream->skip(2);
            $partition = $this->ExpressionList();
        }
        if ($this->stream->matchesKeywordSequence('order', 'by')) {
            $this->stream->skip(2);
            $order = $this->OrderByList();
        }
        if ($this->stream->matchesKeyword(['range', 'rows', 'groups'])) {
            $frame = $this->WindowFrameClause();
        }

        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        return new nodes\WindowDefinition($refName, $partition, $order, $frame);
    }

    protected function WindowFrameClause()
    {
        $frameType  = $this->stream->expect(Token::TYPE_KEYWORD, ['range', 'rows', 'groups']);
        $tokenStart = $this->stream->getCurrent();
        $exclusion  = null;
        if (!$tokenStart->matches(Token::TYPE_KEYWORD, 'between')) {
            $start = $this->WindowFrameBound();
            $end   = null;

        } else {
            $this->stream->next();
            $start = $this->WindowFrameBound();
            $this->stream->expect(Token::TYPE_KEYWORD, 'and');
            $end   = $this->WindowFrameBound();
        }

        // opt_window_exclusion_clause from gram.y
        if ($this->stream->matchesKeyword('exclude')) {
            $this->stream->next();
            $first = $this->stream->expect(Token::TYPE_KEYWORD, ['current', 'group', 'ties', 'no']);
            switch ($first->getValue()) {
                case 'current':
                    $this->stream->expect(Token::TYPE_KEYWORD, 'row');
                    $exclusion = 'current row';
                    break;
                case 'no':
                    $this->stream->expect(Token::TYPE_KEYWORD, 'others');
                    // EXCLUDE NO OTHERS is noise
                    break;
                default:
                    $exclusion = $first->getValue();
            }
        }

        // Repackage exceptions thrown in WindowFrameClause constructor as syntax ones and provide context
        try {
            return new nodes\WindowFrameClause($frameType->getValue(), $start, $end, $exclusion);

        } catch (exceptions\InvalidArgumentException $e) {
            throw exceptions\SyntaxException::atPosition(
                $e->getMessage(),
                $this->stream->getSource(),
                $tokenStart->getPosition()
            );
        }
    }

    protected function WindowFrameBound()
    {
        static $checks = [
            ['unbounded', 'preceding'],
            ['unbounded', 'following'],
            ['current', 'row']
        ];

        foreach ($checks as $check) {
            if ($this->stream->matchesKeywordSequence(...$check)) {
                $this->stream->skip(2);
                return new nodes\WindowFrameBound('current' === $check[0] ? 'current row' : $check[1]);
            }
        }

        $value     = $this->Expression();
        $direction = $this->stream->expect(Token::TYPE_KEYWORD, ['preceding', 'following'])->getValue();
        return new nodes\WindowFrameBound($direction, $value);
    }

    protected function ExpressionList()
    {
        $expressions = new nodes\lists\ExpressionList([$this->Expression()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $expressions[] = $this->Expression();
        }

        return $expressions;
    }

    protected function Expression()
    {
        $terms = [$this->LogicalExpressionTerm()];

        while ($this->stream->matchesKeyword('or')) {
            $this->stream->next();

            $terms[] = $this->LogicalExpressionTerm();
        }

        if (1 === count($terms)) {
            return $terms[0];
        }

        return new nodes\expressions\LogicalExpression($terms, 'or');
    }

    protected function LogicalExpressionTerm()
    {
        $factors = [$this->LogicalExpressionFactor()];

        while ($this->stream->matchesKeyword('and')) {
            $this->stream->next();

            $factors[] = $this->LogicalExpressionFactor();
        }

        if (1 === count($factors)) {
            return $factors[0];
        }

        return new nodes\expressions\LogicalExpression($factors, 'and');
    }

    protected function LogicalExpressionFactor()
    {
        if ($this->stream->matchesKeyword('not')) {
            $this->stream->next();
            return new nodes\expressions\OperatorExpression('not', null, $this->LogicalExpressionFactor());
        }
        return $this->IsWhateverExpression();
    }

    /**
     * In Postgres 9.5+ all comparison operators have the same precedence and are non-associative
     *
     * @param bool $restricted
     * @return nodes\ScalarExpression
     */
    protected function Comparison($restricted = false)
    {
        $argument = $restricted
                    ? $this->GenericOperatorExpression(true)
                    : $this->PatternMatchingExpression();

        if (
            $this->stream->matchesSpecialChar(['<', '>', '='])
            || $this->stream->matches(Token::TYPE_INEQUALITY)
        ) {
            return new nodes\expressions\OperatorExpression(
                $this->stream->next()->getValue(),
                $argument,
                $restricted ? $this->GenericOperatorExpression(true) : $this->PatternMatchingExpression()
            );
        }

        return $argument;
    }

    protected function PatternMatchingExpression()
    {
        static $checks = [
            ['like'],
            ['not', 'like'],
            ['ilike'],
            ['not', 'ilike'],
            // the following cannot be applied to subquery operators
            ['similar', 'to'],
            ['not', 'similar', 'to']
        ];

        $string = $this->OverlapsExpression();

        // speedup
        if (!$this->stream->matchesKeyword(['like', 'ilike', 'not', 'similar'])) {
            return $string;
        }

        foreach ($checks as $checkIdx => $check) {
            if ($this->stream->matchesKeywordSequence(...$check)) {
                $this->stream->skip(count($check));

                $escape = null;
                if ($checkIdx < 4 && $this->stream->matchesKeyword(self::$subType)) {
                    $pattern = $this->SubqueryExpression();

                } else {
                    $pattern = $this->OverlapsExpression();
                    if ($this->stream->matchesKeyword('escape')) {
                        $this->stream->next();
                        $escape = $this->OverlapsExpression();
                    }
                }

                return new nodes\expressions\PatternMatchingExpression(
                    $string,
                    $pattern,
                    implode(' ', $check),
                    $escape
                );
            }
        }

        return $string;
    }

    protected function SubqueryExpression()
    {
        $type  = $this->stream->expect(Token::TYPE_KEYWORD, ['any', 'all', 'some'])->getValue();
        $check = $this->checkContentsOfParentheses();
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
        if ('select' === $check) {
            $operand = $this->SelectStatement();
        } else {
            $operand = $this->Expression();
        }
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        if ('select' === $check) {
            return new nodes\expressions\SubselectExpression($operand, $type);
        } else {
            return new nodes\expressions\FunctionExpression(
                $type,
                new nodes\lists\FunctionArgumentList([$operand])
            );
        }
    }

    protected function OverlapsExpression()
    {
        $left = $this->BetweenExpression();

        if (
            !$left instanceof nodes\expressions\RowExpression
            || !$this->stream->matchesKeyword('overlaps')
        ) {
            return $left;
        }

        $token = $this->stream->next();
        $right = $this->RowConstructor();

        if (count($left) != 2) {
            throw new exceptions\SyntaxException(
                "Wrong number of parameters on left side of " . $token
            );
        } elseif (count($right) != 2) {
            throw new exceptions\SyntaxException(
                "Wrong number of parameters on right side of " . $token
            );
        }

        return new nodes\expressions\OperatorExpression('overlaps', $left, $right);
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
     *
     * @return nodes\ScalarExpression
     */
    protected function BetweenExpression()
    {
        static $checks = [
            ['between', 'symmetric'],
            ['between', 'asymmetric'],
            ['not', 'between', 'symmetric'],
            ['not', 'between', 'asymmetric'],
            ['between'],
            ['not', 'between']
        ];

        $value = $this->InExpression();

        if ($this->stream->matchesKeyword(['between', 'not'])) { // speedup
            foreach ($checks as $check) {
                if ($this->stream->matchesKeywordSequence(...$check)) {
                    $this->stream->skip(count($check));
                    $left  = $this->GenericOperatorExpression(true);
                    $this->stream->expect(Token::TYPE_KEYWORD, 'and');
                    // right argument of BETWEEN is defined as 'b_expr' in pre-9.5 grammar and as 'a_expr' afterwards
                    $right = $this->GenericOperatorExpression(false);
                    $value = new nodes\expressions\BetweenExpression($value, $left, $right, implode(' ', $check));
                    break;
                }
            }
        }

        return $value;
    }

    protected function RestrictedExpression()
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
     *
     * @return nodes\expressions\InExpression
     */
    protected function InExpression()
    {
        $left = $this->GenericOperatorExpression();

        while (
            $this->stream->matchesKeyword('in')
               || $this->stream->matchesKeyword('not')
                  && $this->stream->look(1)->matches(Token::TYPE_KEYWORD, 'in')
        ) {
            $operator = $this->stream->next()->getValue();
            if ('not' === $operator) {
                $operator .= ' ' . $this->stream->next()->getValue();
            }

            $check = $this->checkContentsOfParentheses();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
            if ('select' === $check) {
                $right = $this->SelectStatement();
            } else {
                $right = $this->ExpressionList();
            }
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

            $left = new nodes\expressions\InExpression($left, $right, $operator);
        }

        return $left;
    }


    /**
     * Handles infix and postfix operators
     *
     * @param bool $restricted
     * @return nodes\expressions\OperatorExpression
     */
    protected function GenericOperatorExpression($restricted = false)
    {
        $leftOperand = $this->GenericOperatorTerm($restricted);

        while (
            ($op = $this->matchesOperator())
               || $this->stream->matches(Token::TYPE_SPECIAL, self::$mathOp)
                   && $this->stream->look(1)->matches(Token::TYPE_KEYWORD, self::$subType)
        ) {
            if ($op) {
                $operator = $this->Operator();
            } else {
                $operator = $this->stream->next()->getValue();
            }
            if (!$op || $this->stream->matchesKeyword(self::$subType)) {
                // subquery operator
                $leftOperand = new nodes\expressions\OperatorExpression(
                    $operator,
                    $leftOperand,
                    $this->SubqueryExpression()
                );

            } elseif (!$this->matchesExpressionStart()) {
                // postfix operator
                return new nodes\expressions\OperatorExpression($operator, $leftOperand, null);

            } else {
                $leftOperand = new nodes\expressions\OperatorExpression(
                    $operator,
                    $leftOperand,
                    $this->GenericOperatorTerm($restricted)
                );
            }
        }

        return $leftOperand;
    }

    protected function GenericOperatorTerm($restricted = false)
    {
        // prefix operator(s)
        while ($this->matchesOperator()) {
            $operators[] = $this->Operator();
        }
        $term = $this->ArithmeticExpression($restricted);
        // prefix operators are left-associative
        while (!empty($operators)) {
            $term = new nodes\expressions\OperatorExpression(array_pop($operators), null, $term);
        }

        return $term;
    }

    /**
     * @param bool $all Whether to match qual_Op or qual_all_Op production
     *                  (the latter allows mathematical operators)
     * @return string
     */
    protected function Operator($all = false)
    {
        if (
            $this->stream->matches(Token::TYPE_OPERATOR)
            || $all && $this->stream->matches(Token::TYPE_SPECIAL, self::$mathOp)
        ) {
            return $this->stream->next()->getValue();
        }

        // we don't really give a fuck about qualified operator's structure, so just return it as string
        $operator = $this->stream->expect(Token::TYPE_KEYWORD, 'operator')->getValue()
                    . $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(')->getValue();
        while (
            $this->stream->matchesAnyType(
                Token::TYPE_IDENTIFIER,
                Token::TYPE_UNRESERVED_KEYWORD,
                Token::TYPE_COL_NAME_KEYWORD
            )
        ) {
            // ColId
            $operator .= new nodes\Identifier($this->stream->next());
            $operator .= $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '.')->getValue();
        }
        if ($this->stream->matches(Token::TYPE_SPECIAL, self::$mathOp)) {
            $operator .= $this->stream->next()->getValue();
        } else {
            $operator .= $this->stream->expect(Token::TYPE_OPERATOR)->getValue();
        }
        $operator .= $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')')->getValue();

        return $operator;
    }

    protected function IsWhateverExpression($restricted = false)
    {
        $operand = $this->Comparison($restricted);

        if ($restricted) {
            $checks = [];

        } else {
            $checks = [
                ['null'],
                ['true'],
                ['false'],
                ['unknown'],
                ['normalized'],
                [['nfc', 'nfd', 'nfkc', 'nfkd'], 'normalized']
            ];
        }
        $checks = array_merge($checks, [['document']]);

        while (
            $this->stream->matchesKeyword('is')
               || !$restricted && $this->stream->matchesKeyword(['notnull', 'isnull'])
        ) {
            $operator = $this->stream->next()->getValue();
            if ('notnull' === $operator) {
                $operand = new nodes\expressions\OperatorExpression('is not null', $operand, null);
                continue;
            } elseif ('isnull' === $operator) {
                $operand = new nodes\expressions\OperatorExpression('is null', $operand, null);
                continue;
            }

            if ($this->stream->matchesKeyword('not')) {
                $operator .= ' ' . $this->stream->next()->getValue();
            }

            foreach ($checks as $check) {
                if ($this->stream->matchesKeywordSequence(...$check)) {
                    for ($i = 0; $i < count($check); $i++) {
                        $operator .= ' ' . $this->stream->next()->getValue();
                    }
                    $operand = new nodes\expressions\OperatorExpression($operator, $operand, null);
                    continue 2;
                }
            }

            if ($this->stream->matchesKeywordSequence('distinct', 'from')) {
                $this->stream->skip(2);
                // 'is distinct from' requires parentheses
                return new nodes\expressions\OperatorExpression(
                    $operator . ' distinct from',
                    $operand,
                    $this->ArithmeticExpression($restricted)
                );
            }

            if (
                $this->stream->matchesKeyword('of')
                && $this->stream->look()->matches(Token::TYPE_SPECIAL_CHAR, '(')
            ) {
                $this->stream->skip(2);
                $right = $this->TypeList();
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
                $operand = new nodes\expressions\IsOfExpression($operand, $right, $operator . ' of');
                continue;
            }

            throw new exceptions\SyntaxException('Unexpected ' . $this->stream->getCurrent());
        }

        return $operand;
    }

    protected function TypeList()
    {
        $types = new nodes\lists\TypeList([$this->TypeName()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $types[] = $this->TypeName();
        }

        return $types;
    }

    protected function TypeName()
    {
        $setOf  = false;
        $bounds = [];
        if ($this->stream->matchesKeyword('setof')) {
            $this->stream->next();
            $setOf = true;
        }

        $typeName = $this->SimpleTypeName();
        $typeName->setSetOf($setOf);

        if ($this->stream->matchesKeyword('array')) {
            $this->stream->next();
            if (!$this->stream->matchesSpecialChar('[')) {
                $bounds[] = -1;
            } else {
                $this->stream->next();
                $bounds[] = $this->stream->expect(Token::TYPE_INTEGER)->getValue();
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ']');
            }

        } else {
            while ($this->stream->matchesSpecialChar('[')) {
                $this->stream->next();
                if ($this->stream->matches(Token::TYPE_INTEGER)) {
                    $bounds[] = $this->stream->next()->getValue();
                } else {
                    $bounds[] = -1;
                }
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ']');
            }
        }
        $typeName->setBounds($bounds);

        return $typeName;
    }

    /**
     * @return nodes\TypeName
     * @throws exceptions\SyntaxException
     */
    protected function SimpleTypeName()
    {
        if (
            null !== ($typeName = $this->IntervalTypeName())
            || null !== ($typeName = $this->DateTimeTypeName())
            || null !== ($typeName = $this->CharacterTypeName())
            || null !== ($typeName = $this->BitTypeName())
            || null !== ($typeName = $this->NumericTypeName())
            || null !== ($typeName = $this->GenericTypeName())
        ) {
            return $typeName;
        }

        throw exceptions\SyntaxException::atPosition(
            'Expecting type name',
            $this->stream->getSource(),
            $this->stream->getCurrent()->getPosition()
        );
    }

    protected function NumericTypeName()
    {
        static $mapping = [
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

        if (
            $this->stream->matchesKeyword(['int', 'integer', 'smallint', 'bigint', 'real', 'float', 'decimal', 'dec', 'numeric', 'boolean'])
            || $this->stream->matchesKeywordSequence('double', 'precision')
        ) {
            $typeName  = $this->stream->next()->getValue();
            $modifiers = null;
            if ('double' === $typeName) {
                // "double precision"
                $typeName .= ' ' . $this->stream->next()->getValue();

            } else {
                if ('float' === $typeName) {
                    $floatName = 'float8';
                    if ($this->stream->matchesSpecialChar('(')) {
                        $this->stream->next();
                        $precisionToken = $this->stream->expect(Token::TYPE_INTEGER);
                        $precision      = $precisionToken->getValue();
                        if ($precision < 1) {
                            throw exceptions\SyntaxException::atPosition(
                                'Precision for type float must be at least 1 bit',
                                $this->stream->getSource(),
                                $precisionToken->getPosition()
                            );
                        } elseif ($precision <= 24) {
                            $floatName = 'float4';
                        } elseif ($precision <= 53) {
                            $floatName = 'float8';
                        } else {
                            throw exceptions\SyntaxException::atPosition(
                                'Precision for type float must be less than 54 bits',
                                $this->stream->getSource(),
                                $precisionToken->getPosition()
                            );
                        }
                        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
                    }
                    return new nodes\TypeName(new nodes\QualifiedName(['pg_catalog', $floatName]));

                } elseif ('decimal' === $typeName || 'dec' === $typeName || 'numeric' === $typeName) {
                    // NB: we explicitly require constants here, per comment in gram.y:
                    // > To avoid parsing conflicts against function invocations, the modifiers
                    // > have to be shown as expr_list here, but parse analysis will only accept
                    // > constants for them.
                    if ($this->stream->matchesSpecialChar('(')) {
                        $this->stream->next();
                        $modifiers = new nodes\lists\TypeModifierList([
                            new nodes\Constant($this->stream->expect(Token::TYPE_INTEGER))
                        ]);
                        if ($this->stream->matchesSpecialChar(',')) {
                            $this->stream->next();
                            $modifiers[] = new nodes\Constant($this->stream->expect(Token::TYPE_INTEGER));
                        }
                        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
                    }
                }
            }
            return new nodes\TypeName(
                new nodes\QualifiedName(['pg_catalog', $mapping[$typeName]]),
                $modifiers
            );
        }

        return null;
    }

    protected function BitTypeName($leading = false)
    {
        if ($this->stream->matchesKeyword('bit')) {
            $typeName  = $this->stream->next()->getValue();
            $modifiers = null;
            if ($this->stream->matchesKeyword('varying')) {
                $this->stream->next();
                $typeName = 'varbit';
            }
            if ($this->stream->matchesSpecialChar('(')) {
                $this->stream->next();
                $modifiers = new nodes\lists\TypeModifierList([
                    new nodes\Constant($this->stream->expect(Token::TYPE_INTEGER))
                ]);
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            }
            // BIT translates to bit(1) *unless* this is a leading typecast
            // where it translates to "any length" (with no modifiers)
            if (!$leading && $typeName === 'bit' && empty($modifiers)) {
                $modifiers = new nodes\lists\TypeModifierList([new nodes\Constant(1)]);
            }
            return new nodes\TypeName(
                new nodes\QualifiedName(['pg_catalog', $typeName]),
                $modifiers
            );
        }

        return null;
    }

    protected function CharacterTypeName($leading = false)
    {
        if (
            $this->stream->matchesKeyword(['character', 'char', 'varchar', 'nchar'])
            || $this->stream->matchesKeyword('national')
               && $this->stream->look(1)->matches(Token::TYPE_KEYWORD, ['character', 'char'])
        ) {
            $typeName  = $this->stream->next()->getValue();
            $varying   = ('varchar' === $typeName);
            $modifiers = null;
            if ('national' === $typeName) {
                $this->stream->next();
            }
            if ('varchar' !== $typeName && $this->stream->matchesKeyword('varying')) {
                $this->stream->next();
                $varying = true;
            }
            if ($this->stream->matchesSpecialChar('(')) {
                $this->stream->next();
                $modifiers = new nodes\lists\TypeModifierList([
                    new nodes\Constant($this->stream->expect(Token::TYPE_INTEGER))
                ]);
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            }
            // CHAR translates to char(1) *unless* this is a leading typecast
            // where it translates to "any length" (with no modifiers)
            if (!$leading && !$varying && null === $modifiers) {
                $modifiers = new nodes\lists\TypeModifierList([new nodes\Constant(1)]);
            }
            $typeNode = new nodes\TypeName(
                new nodes\QualifiedName(['pg_catalog', $varying ? 'varchar' : 'bpchar']),
                $modifiers
            );

            return $typeNode;
        }

        return null;
    }

    protected function DateTimeTypeName()
    {
        if ($this->stream->matchesKeyword(['time', 'timestamp'])) {
            $typeName  = $this->stream->next()->getValue();
            $modifiers = null;
            if ($this->stream->matchesSpecialChar('(')) {
                $this->stream->next();
                $modifiers = new nodes\lists\TypeModifierList([
                    new nodes\Constant($this->stream->expect(Token::TYPE_INTEGER))
                ]);
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            }

            if ($this->stream->matchesKeywordSequence(['with', 'without'], 'time', 'zone')) {
                if ('with' === $this->stream->next()->getValue()) {
                    $typeName .= 'tz';
                }
                $this->stream->skip(2);
            }
            return new nodes\TypeName(new nodes\QualifiedName(['pg_catalog', $typeName]), $modifiers);
        }

        return null;
    }


    protected function IntervalTypeName($leading = false)
    {
        if ($this->stream->matchesKeyword('interval')) {
            $token     = $this->stream->next();
            $modifiers = null;
            $operand   = null;
            if ($this->stream->matchesSpecialChar('(')) {
                $this->stream->next();
                $modifiers = new nodes\lists\TypeModifierList([
                    new nodes\Constant($this->stream->expect(Token::TYPE_INTEGER))
                ]);
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            }
            if ($leading) {
                $operand = new nodes\Constant($this->stream->expect(Token::TYPE_STRING));
            }

            if (
                !$modifiers
                && $this->stream->matchesKeyword(['year', 'month', 'day', 'hour', 'minute', 'second'])
            ) {
                $trailing  = [$this->stream->next()->getValue()];
                $second    = 'second' === $trailing[0];
                if ($this->stream->matchesKeyword('to')) {
                    $toToken    = $this->stream->next();
                    $trailing[] = 'to';
                    if ('year' === $trailing[0]) {
                        $end = $this->stream->expect(Token::TYPE_KEYWORD, 'month');
                    } elseif ('day' === $trailing[0]) {
                        $end = $this->stream->expect(Token::TYPE_KEYWORD, ['hour', 'minute', 'second']);
                    } elseif ('hour' === $trailing[0]) {
                        $end = $this->stream->expect(Token::TYPE_KEYWORD, ['minute', 'second']);
                    } elseif ('minute' === $trailing[0]) {
                        $end = $this->stream->expect(Token::TYPE_KEYWORD, 'second');
                    } else {
                        throw new exceptions\SyntaxException('Unexpected ' . $toToken);
                    }
                    $second     = 'second' === $end->getValue();
                    $trailing[] = $end->getValue();
                }

                if ($second && $this->stream->matchesSpecialChar('(')) {
                    if (null !== $modifiers) {
                        throw new exceptions\SyntaxException('Interval precision specified twice for ' . $token);
                    }
                    $this->stream->next();
                    $modifiers = new nodes\lists\TypeModifierList([
                        new nodes\Constant($this->stream->expect(Token::TYPE_INTEGER))
                    ]);
                    $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
                }
            }
            $typeNode = new nodes\IntervalTypeName($modifiers);
            if (!empty($trailing)) {
                $typeNode->setMask(implode(' ', $trailing));
            }

            return $operand ? new nodes\expressions\TypecastExpression($operand, $typeNode) : $typeNode;
        }

        return null;
    }

    protected function GenericTypeName()
    {
        if (
            $this->stream->matchesAnyType(
                Token::TYPE_IDENTIFIER,
                Token::TYPE_UNRESERVED_KEYWORD,
                Token::TYPE_TYPE_FUNC_NAME_KEYWORD
            )
        ) {
            $typeName  = [new nodes\Identifier($this->stream->next())];
            $modifiers = null;
            while ($this->stream->matchesSpecialChar('.')) {
                $this->stream->next();
                if ($this->stream->matches(Token::TYPE_IDENTIFIER)) {
                    $typeName[] = new nodes\Identifier($this->stream->next());
                } else {
                    // any keyword goes, see ColLabel
                    $typeName[] = new nodes\Identifier($this->stream->expect(Token::TYPE_KEYWORD));
                }
            }

            if ($this->stream->matchesSpecialChar('(')) {
                $this->stream->next();
                $modifiers = new nodes\lists\TypeModifierList([$this->GenericTypeModifier()]);
                while ($this->stream->matchesSpecialChar(',')) {
                    $this->stream->next();
                    $modifiers[] = $this->GenericTypeModifier();
                }
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            }

            return new nodes\TypeName(new nodes\QualifiedName($typeName), $modifiers);
        }

        return null;
    }

    /**
     * Gets a type modifier for a "generic" type
     *
     * Type modifiers here are allowed according to typenameTypeMod() function from
     * src/backend/parser/parse_type.c
     *
     * @return nodes\Constant|nodes\Identifier
     * @throws exceptions\SyntaxException
     */
    protected function GenericTypeModifier()
    {
        // Let's keep most common case at the top
        if ($this->stream->matchesAnyType(Token::TYPE_INTEGER, Token::TYPE_FLOAT, Token::TYPE_STRING)) {
            return new nodes\Constant($this->stream->next());

        } elseif (
            $this->stream->matchesAnyType(
                Token::TYPE_IDENTIFIER,
                Token::TYPE_UNRESERVED_KEYWORD,
                Token::TYPE_TYPE_FUNC_NAME_KEYWORD
            )
        ) {
            // allows ColId
            return new nodes\Identifier($this->stream->next());

        } else {
            throw new exceptions\SyntaxException(
                "Expecting a constant or an identifier, got " . $this->stream->getCurrent()
            );
        }
    }

    protected function ArithmeticExpression($restricted = false)
    {
        $leftOperand = $this->ArithmeticTerm($restricted);

        while ($this->stream->matchesSpecialChar(['+', '-'])) {
            $operator = $this->stream->next()->getValue();
            $leftOperand = new nodes\expressions\OperatorExpression(
                $operator,
                $leftOperand,
                $this->ArithmeticTerm($restricted)
            );
        }

        return $leftOperand;
    }

    protected function ArithmeticTerm($restricted = false)
    {
        $leftOperand = $this->ArithmeticMultiplier($restricted);

        while ($this->stream->matchesSpecialChar(['*', '/', '%'])) {
            $operator = $this->stream->next()->getValue();
            $leftOperand = new nodes\expressions\OperatorExpression(
                $operator,
                $leftOperand,
                $this->ArithmeticMultiplier($restricted)
            );
        }

        return $leftOperand;
    }

    protected function ArithmeticMultiplier($restricted = false)
    {
        $leftOperand = $restricted
                       ? $this->UnaryPlusMinusExpression()
                       : $this->AtTimeZoneExpression();

        while ($this->stream->matchesSpecialChar('^')) {
            $operator    = $this->stream->next()->getValue();
            $leftOperand = new nodes\expressions\OperatorExpression(
                $operator,
                $leftOperand,
                $restricted ? $this->UnaryPlusMinusExpression() : $this->AtTimeZoneExpression()
            );
        }

        return $leftOperand;
    }

    protected function AtTimeZoneExpression()
    {
        $left = $this->CollateExpression();
        if ($this->stream->matchesKeywordSequence('at', 'time', 'zone')) {
            $this->stream->skip(3);
            return new nodes\expressions\OperatorExpression('at time zone', $left, $this->CollateExpression());
        }
        return $left;
    }

    protected function CollateExpression()
    {
        $left = $this->UnaryPlusMinusExpression();
        if ($this->stream->matchesKeyword('collate')) {
            $this->stream->next();
            return new nodes\expressions\CollateExpression($left, $this->QualifiedName());
        }
        return $left;
    }

    protected function UnaryPlusMinusExpression()
    {
        if ($this->stream->matchesSpecialChar(['+', '-'])) {
            $token    = $this->stream->next();
            $operator = $token->getValue();
            $operand  = $this->UnaryPlusMinusExpression();
            if (
                !$operand instanceof nodes\Constant
                || !in_array($operand->type, [Token::TYPE_INTEGER, Token::TYPE_FLOAT])
                || '-' !== $operator
            ) {
                return new nodes\expressions\OperatorExpression($operator, null, $operand);

            } else {
                if ('-' === $operand->value[0]) {
                    return new nodes\Constant(new Token($operand->type, substr($operand->value, 1), $token->getPosition()));
                } else {
                    return new nodes\Constant(new Token($operand->type, '-' . $operand->value, $token->getPosition()));
                }
            }
        }
        return $this->TypecastExpression();
    }

    protected function TypecastExpression()
    {
        $left = $this->ExpressionAtom();

        while ($this->stream->matches(Token::TYPE_TYPECAST)) {
            $this->stream->next();
            $left = new nodes\expressions\TypecastExpression($left, $this->TypeName());
        }

        return $left;
    }

    protected function ExpressionAtom()
    {
        $token = $this->stream->getCurrent();
        if ($token->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
            switch ($this->checkContentsOfParentheses()) {
                case 'row':
                    return $this->RowConstructor();

                case 'select':
                    $atom = new nodes\expressions\SubselectExpression($this->SelectWithParentheses());
                    break;

                case 'expression':
                default:
                    $this->stream->next();
                    $atom = $this->Expression();
                    $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            }

        } elseif ($token->matches(Token::TYPE_KEYWORD)) {
            switch ($token->getValue()) {
                case 'row':
                    if ($this->stream->look()->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
                        return $this->RowConstructor();
                    }
                    break;

                case 'array':
                    return $this->ArrayConstructor();

                case 'exists':
                    $this->stream->next();
                    return new nodes\expressions\SubselectExpression($this->SelectWithParentheses(), 'exists');

                case 'case':
                    return $this->CaseExpression();

                case 'grouping':
                    return $this->GroupingExpression();

                case 'true':
                case 'false':
                case 'null':
                    return new nodes\Constant($this->stream->next());
            }

        } elseif ($token->matches(Token::TYPE_PARAMETER)) {
            $atom = new nodes\Parameter($this->stream->next());

        } elseif ($token->matches(Token::TYPE_LITERAL)) {
            return new nodes\Constant($this->stream->next());
        }

        if (!isset($atom)) {
            if ($this->matchesTypecast()) {
                return $this->LeadingTypecast();

            } elseif ($this->matchesFunctionCall()) {
                return $this->FunctionExpression();

            } else {
                return $this->ColumnReference();
            }
        }

        if ($indirection = $this->Indirection()) {
            return new nodes\Indirection($indirection, $atom);
        }

        return $atom;
    }

    protected function RowList()
    {
        $list = new nodes\lists\RowList([$this->RowConstructorNoKeyword()]);
        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $list[] = $this->RowConstructorNoKeyword();
        }
        return $list;
    }

    protected function RowConstructor()
    {
        if ($this->stream->matchesKeyword('row')) {
            $this->stream->next();
        }
        // ROW() is only possible with the keyword, 'VALUES ()' is a syntax error
        if (
            $this->stream->matchesSpecialChar('(')
            && $this->stream->look()->matches(Token::TYPE_SPECIAL_CHAR, ')')
        ) {
            $this->stream->skip(2);
            return new nodes\expressions\RowExpression([]);
        }

        return $this->RowConstructorNoKeyword();
    }

    protected function RowConstructorNoKeyword()
    {
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
        $fields = $this->ExpressionListWithDefault();
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        return new nodes\expressions\RowExpression($fields);
    }

    protected function ArrayConstructor()
    {
        $this->stream->expect(Token::TYPE_KEYWORD, 'array');
        if (!$this->stream->matchesSpecialChar(['[', '('])) {
            throw exceptions\SyntaxException::expectationFailed(
                Token::TYPE_SPECIAL_CHAR,
                ['[', '('],
                $this->stream->getCurrent(),
                $this->stream->getSource()
            );

        } elseif ('(' === $this->stream->getCurrent()->getValue()) {
            return new nodes\expressions\SubselectExpression($this->SelectWithParentheses(), 'array');

        } else {
            return new nodes\expressions\ArrayExpression($this->ArrayExpression());
        }
    }

    protected function ArrayExpression()
    {
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '[');
        if ($this->stream->matchesSpecialChar(']')) {
            $expression = [];

        } elseif (!$this->stream->matchesSpecialChar('[')) {
            $expression = $this->ExpressionList();

        } else {
            $expression = [$this->ArrayExpression()];
            while ($this->stream->matchesSpecialChar(',')) {
                $this->stream->next();
                $expression[] = $this->ArrayExpression();
            }
        }
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ']');

        return $expression;
    }

    protected function CaseExpression()
    {
        $argument    = null;
        $whenClauses = [];
        $elseClause  = null;

        $this->stream->expect(Token::TYPE_KEYWORD, 'case');
        // "simple" variant?
        if (!$this->stream->matchesKeyword('when')) {
            $argument = $this->Expression();
        }

        // requires at least one WHEN clause
        do {
            $this->stream->expect(Token::TYPE_KEYWORD, 'when');
            $when = $this->Expression();
            $this->stream->expect(Token::TYPE_KEYWORD, 'then');
            $then = $this->Expression();
            $whenClauses[] = new nodes\expressions\WhenExpression($when, $then);
        } while ($this->stream->matchesKeyword('when'));

        // may have an ELSE clause
        if ($this->stream->matchesKeyword('else')) {
            $this->stream->next();
            $elseClause = $this->Expression();
        }
        $this->stream->expect(Token::TYPE_KEYWORD, 'end');

        return new nodes\expressions\CaseExpression($whenClauses, $elseClause, $argument);
    }

    protected function GroupingExpression()
    {
        $this->stream->expect(Token::TYPE_KEYWORD, 'grouping');
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
        $expression = new nodes\expressions\GroupingExpression($this->ExpressionList());
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        return $expression;
    }

    protected function LeadingTypecast()
    {
        if (null !== ($typeCast = $this->IntervalTypeName(true))) {
            // interval is a special case since its options may come *after* string constant
            return $typeCast;
        }

        if (
            null !== ($typeName = $this->DateTimeTypeName())
            || null !== ($typeName = $this->CharacterTypeName(true))
            || null !== ($typeName = $this->BitTypeName(true))
            || null !== ($typeName = $this->NumericTypeName())
            || null !== ($typeName = $this->GenericTypeName())
        ) {
            $constant = new nodes\Constant($this->stream->expect(Token::TYPE_STRING));
            return new nodes\expressions\TypecastExpression($constant, $typeName);
        }

        throw new exceptions\SyntaxException('Expecting type name, got ' . $this->stream->getCurrent());
    }

    protected function SystemFunctionCallNoParens()
    {
        static $mapFunctions = [
            'current_role'    => 'current_user',
            'user'            => 'current_user',
            'current_catalog' => 'current_database'
        ];

        if (
            !$this->stream->matchesKeyword([
                'current_date', 'current_role', 'current_user', 'session_user',
                'user', 'current_catalog', 'current_schema'
            ])
        ) {
            return null;
        }

        $funcName = $this->stream->next()->getValue();
        if ('current_date' === $funcName) {
            // we convert to 'now'::date instead of 'now'::text::date, since the latter is only
            // needed for rules, default values and such. we don't do these
            return new nodes\expressions\TypecastExpression(
                new nodes\Constant('now'),
                new nodes\TypeName(new nodes\QualifiedName(['pg_catalog', 'date']))
            );

        } elseif (isset($mapFunctions[$funcName])) {
            return new nodes\FunctionCall(new nodes\QualifiedName(['pg_catalog', $mapFunctions[$funcName]]));

        } else {
            return new nodes\FunctionCall(new nodes\QualifiedName(['pg_catalog', $funcName]));
        }
    }

    protected function SystemFunctionCallOptionalParens()
    {
        static $mapTypes = [
            'current_time'      => 'timetz',
            'current_timestamp' => 'timestamptz',
            'localtime'         => 'time',
            'localtimestamp'    => 'timestamp'
        ];

        if (!$this->stream->matchesKeyword(['current_time', 'current_timestamp', 'localtime', 'localtimestamp'])) {
            return null;
        }

        $funcName = $this->stream->next()->getValue();
        if (!$this->stream->matchesSpecialChar('(')) {
            $modifiers = null;
        } else {
            $this->stream->next();
            $modifiers = new nodes\lists\TypeModifierList([
                new nodes\Constant($this->stream->expect(Token::TYPE_INTEGER))
            ]);
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
        }
        $typeName = new nodes\TypeName(
            new nodes\QualifiedName(['pg_catalog', $mapTypes[$funcName]]),
            $modifiers
        );
        return new nodes\expressions\TypecastExpression(new nodes\Constant('now'), $typeName);
    }

    protected function SystemFunctionCallRequiredParens()
    {
        if (
            !$this->stream->matchesKeyword([
                'cast', 'extract', 'overlay', 'position', 'substring', 'treat', 'trim',
                'nullif', 'coalesce', 'greatest', 'least', 'xmlconcat', 'xmlelement',
                'xmlexists', 'xmlforest', 'xmlparse', 'xmlpi', 'xmlroot', 'xmlserialize',
                'normalize'
            ])
        ) {
            return null;
        }
        $funcName  = $this->stream->next()->getValue();
        $arguments = [];
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');

        switch ($funcName) {
            case 'treat':
                // TREAT is "a bit" undocumented and buggy:
                // select treat('' as public.hstore); -> ERROR: function pg_catalog.hstore(unknown) does not exist
                // can be traced to revision 68d9fbeb5511d846ce3a6f66b8955d3ca55a4b76 from 2002
                throw new exceptions\NotImplementedException('TREAT() function support is not implemented');

            case 'cast':
                $value = $this->Expression();
                $this->stream->expect(Token::TYPE_KEYWORD, 'as');
                $funcNode = new nodes\expressions\TypecastExpression($value, $this->TypeName());
                break;

            case 'extract':
                $funcName = 'date_part';
                if (
                    $this->stream->matchesKeyword(['year', 'month', 'day', 'hour', 'minute', 'second'])
                    || $this->stream->matches(Token::TYPE_STRING)
                ) {
                    $arguments[] = new nodes\Constant($this->stream->next()->getValue());
                } else {
                    $arguments[] = new nodes\Constant($this->stream->expect(Token::TYPE_IDENTIFIER)->getValue());
                }
                $this->stream->expect(Token::TYPE_KEYWORD, 'from');
                $arguments[] = $this->Expression();
                break;

            case 'overlay':
                $arguments[] = $this->Expression();
                $this->stream->expect(Token::TYPE_KEYWORD, 'placing');
                $arguments[] = $this->Expression();
                $this->stream->expect(Token::TYPE_KEYWORD, 'from');
                $arguments[] = $this->Expression();
                if ($this->stream->matchesKeyword('for')) {
                    $this->stream->next();
                    $arguments[] = $this->Expression();
                }
                break;

            case 'position':
                // position(A in B) = "position"(B, A)
                $arguments[] = $this->RestrictedExpression();
                $this->stream->expect(Token::TYPE_KEYWORD, 'in');
                array_unshift($arguments, $this->RestrictedExpression());
                break;

            case 'substring':
                $arguments = $this->SubstringFunctionArguments();
                break;

            case 'trim':
                list($funcName, $arguments) = $this->TrimFunctionArguments();
                break;

            case 'nullif': // only two arguments, so don't use ExpressionList()
                $arguments = new nodes\lists\FunctionArgumentList([$this->Expression()]);
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ',');
                $arguments[] = $this->Expression();
                $funcNode    = new nodes\FunctionCall('nullif', $arguments);
                break;

            case 'xmlelement':
                $funcNode = $this->XmlElementFunction();
                break;

            case 'xmlexists':
                $arguments = $this->XmlExistsArguments();
                break;

            case 'xmlforest':
                $funcNode = new nodes\xml\XmlForest($this->XmlAttributeList());
                break;

            case 'xmlparse':
                $docOrContent = $this->stream->expect(Token::TYPE_KEYWORD, ['document', 'content'])->getValue();
                $value        = $this->Expression();
                $preserve     = false;
                if ($this->stream->matchesKeywordSequence('preserve', 'whitespace')) {
                    $preserve = true;
                    $this->stream->next();
                    $this->stream->next();
                } elseif ($this->stream->matchesKeywordSequence('strip', 'whitespace')) {
                    $this->stream->next();
                    $this->stream->next();
                }
                $funcNode = new nodes\xml\XmlParse($docOrContent, $value, $preserve);
                break;

            case 'xmlpi':
                $this->stream->expect(Token::TYPE_KEYWORD, 'name');
                if ($this->stream->matches(Token::TYPE_KEYWORD)) {
                    $name = new nodes\Identifier($this->stream->next());
                } else {
                    $name = new nodes\Identifier($this->stream->expect(Token::TYPE_IDENTIFIER));
                }
                $content = null;
                if ($this->stream->matchesSpecialChar(',')) {
                    $this->stream->next();
                    $content = $this->Expression();
                }

                $funcNode = new nodes\xml\XmlPi($name, $content);
                break;

            case 'xmlroot':
                $funcNode = $this->XmlRoot();
                break;

            case 'xmlserialize':
                $docOrContent = $this->stream->expect(Token::TYPE_KEYWORD, ['document', 'content'])->getValue();
                $value        = $this->Expression();
                $this->stream->expect(Token::TYPE_KEYWORD, 'as');
                $typeName     = $this->SimpleTypeName();
                $funcNode     = new nodes\xml\XmlSerialize($docOrContent, $value, $typeName);
                break;

            case 'normalize':
                $arguments[] = $this->Expression();
                if ($this->stream->matchesSpecialChar(',')) {
                    $this->stream->skip(1);
                    $form = $this->stream->expect(Token::TYPE_KEYWORD, ['nfc', 'nfd', 'nfkc', 'nfkd']);
                    $arguments[] = new nodes\Constant($form->getValue());
                }
                break;

            default: // 'coalesce', 'greatest', 'least', 'xmlconcat'
                $funcNode = new nodes\FunctionCall(
                    $funcName,
                    new nodes\lists\FunctionArgumentList($this->ExpressionList())
                );
        }

        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
        if (empty($funcNode)) {
            $funcNode = new nodes\FunctionCall(
                new nodes\QualifiedName(['pg_catalog', $funcName]),
                new nodes\lists\FunctionArgumentList($arguments)
            );
        }
        return $funcNode;
    }

    protected function TrimFunctionArguments()
    {
        if (!$this->stream->matchesKeyword(['both', 'leading', 'trailing'])) {
            $funcName = 'btrim';
        } else {
            switch ($this->stream->next()->getValue()) {
                case 'leading':
                    $funcName = 'ltrim';
                    break;
                case 'trailing':
                    $funcName = 'rtrim';
                    break;
                case 'both':
                default:
                    $funcName = 'btrim';
            }
        }

        if ($this->stream->matchesKeyword('from')) {
            $this->stream->next();
            $arguments = $this->ExpressionList();
        } else {
            $first = $this->Expression();
            if ($this->stream->matchesKeyword('from')) {
                $this->stream->next();
                $arguments   = $this->ExpressionList();
                $arguments[] = $first;
            } elseif ($this->stream->matchesSpecialChar(',')) {
                $this->stream->next();
                $arguments = new nodes\lists\ExpressionList([$first]);
                $arguments->merge($this->ExpressionList());
            } else {
                $arguments = [$first];
            }
        }

        return [$funcName, $arguments];
    }

    protected function SubstringFunctionArguments()
    {
        $arguments = new nodes\lists\FunctionArgumentList([$this->Expression()]);
        $from  = $for = null;
        if (!$this->stream->matchesKeyword(['from', 'for'])) {
            if ($this->stream->matchesSpecialChar(',')) {
                $this->stream->next();
            } else {
                $token = $this->stream->getCurrent();
                throw exceptions\SyntaxException::atPosition(
                    "Unexpected {$token}, expecting ',' or 'from' or 'for'",
                    $this->stream->getSource(),
                    $token->getPosition()
                );
            }
            $arguments->merge($this->ExpressionList());

        } else {
            if ('from' === $this->stream->next()->getValue()) {
                $from = $this->Expression();
            } else {
                $for  = $this->Expression();
            }
            if ($this->stream->matchesKeyword(['from', 'for'])) {
                if (!$from && 'from' === $this->stream->getCurrent()->getValue()) {
                    $this->stream->next();
                    $from = $this->Expression();

                } elseif (!$for && 'for' === $this->stream->getCurrent()->getValue()) {
                    $this->stream->next();
                    $for  = $this->Expression();
                }
            }
        }

        if ($from && $for) {
            $arguments->merge([$from, $for]);
        } elseif ($from) {
            $arguments[] = $from;
        } elseif ($for) {
            $arguments->merge([new nodes\Constant(1), $for]);
        }

        return $arguments;
    }

    protected function XmlElementFunction()
    {
        $this->stream->expect(Token::TYPE_KEYWORD, 'name');
        if ($this->stream->matches(Token::TYPE_KEYWORD)) {
            $name = new nodes\Identifier($this->stream->next());
        } else {
            $name = new nodes\Identifier($this->stream->expect(Token::TYPE_IDENTIFIER));
        }
        $attributes = $content = null;
        if ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            if (!$this->stream->matchesKeyword('xmlattributes')) {
                $content = $this->ExpressionList();
            } else {
                $this->stream->next();
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
                $attributes = new nodes\lists\TargetList($this->XmlAttributeList());
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
                if ($this->stream->matchesSpecialChar(',')) {
                    $this->stream->next();
                    $content = $this->ExpressionList();
                }
            }
        }
        return new nodes\xml\XmlElement($name, $attributes, $content);
    }

    protected function XmlRoot()
    {
        $xml = $this->Expression();
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ',');
        $this->stream->expect(Token::TYPE_KEYWORD, 'version');
        if ($this->stream->matchesKeywordSequence('no', 'value')) {
            $version = null;
        } else {
            $version = $this->Expression();
        }
        if (!$this->stream->matchesSpecialChar(',')) {
            $standalone = null;
        } else {
            $this->stream->next();
            $this->stream->expect(Token::TYPE_KEYWORD, 'standalone');
            if ($this->stream->matchesKeywordSequence('no', 'value')) {
                $this->stream->next();
                $this->stream->next();
                $standalone = 'no value';
            } else {
                $standalone = $this->stream->expect(Token::TYPE_KEYWORD, ['yes', 'no'])->getValue();
            }
        }
        return new nodes\xml\XmlRoot($xml, $version, $standalone);
    }

    protected function XmlAttributeList()
    {
        $attributes = [$this->XmlAttribute()];

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $attributes[] = $this->XmlAttribute();
        }

        return $attributes;
    }

    protected function XmlAttribute()
    {
        $value   = $this->Expression();
        $attname = null;
        if ($this->stream->matchesKeyword('as')) {
            $this->stream->next();
            if ($this->stream->matches(Token::TYPE_KEYWORD)) {
                $attname = new nodes\Identifier($this->stream->next());
            } else {
                $attname = new nodes\Identifier($this->stream->expect(Token::TYPE_IDENTIFIER));
            }
        }
        return new nodes\TargetElement($value, $attname);
    }

    protected function FunctionExpression()
    {
        if (null !== ($function = $this->SpecialFunctionCall())) {
            return ($function instanceof nodes\FunctionCall)
                   ? new nodes\expressions\FunctionExpression(
                       is_object($function->name) ? clone $function->name : $function->name,
                       clone $function->arguments,
                       $function->distinct,
                       $function->variadic,
                       clone $function->order
                   )
                   : $function;
        }

        $function    = $this->GenericFunctionCall();
        $withinGroup = false;
        $order       = $filter = $over = null;

        if ($this->stream->matchesKeywordSequence('within', 'group')) {
            if (count($function->order) > 0) {
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
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
            $this->stream->expect(Token::TYPE_KEYWORD, 'order');
            $this->stream->expect(Token::TYPE_KEYWORD, 'by');
            $order = $this->OrderByList();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
        }
        if ($this->stream->matchesKeyword('filter')) {
            $this->stream->next();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
            $this->stream->expect(Token::TYPE_KEYWORD, 'where');
            $filter = $this->Expression();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
        }
        if ($this->stream->matchesKeyword('over')) {
            $this->stream->next();
            $over = $this->WindowSpecification();
        }
        return new nodes\expressions\FunctionExpression(
            is_object($function->name) ? clone $function->name : $function->name,
            clone $function->arguments,
            $function->distinct,
            $function->variadic,
            $order ?: clone $function->order,
            $withinGroup,
            $filter,
            $over
        );
    }

    protected function SpecialFunctionCall()
    {
        if (
            null !== ($funcNode = $this->SystemFunctionCallNoParens())
            || null !== ($funcNode = $this->SystemFunctionCallOptionalParens())
            || null !== ($funcNode = $this->SystemFunctionCallRequiredParens())
        ) {
            return $funcNode;

        } elseif ($this->stream->matchesKeywordSequence('collation', 'for')) {
            $this->stream->skip(2);
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
            $argument = $this->Expression();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            return new nodes\FunctionCall(
                new nodes\QualifiedName(['pg_catalog', 'pg_collation_for']),
                new nodes\lists\FunctionArgumentList([$argument])
            );
        }

        return null;
    }

    protected function GenericFunctionCall()
    {
        $positionalArguments = $namedArguments = [];
        $variadic = $distinct = false;
        $orderBy  = null;

        $funcName = $this->GenericFunctionName();

        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
        if ($this->stream->matchesSpecialChar('*')) {
            $this->stream->next();
            $positionalArguments = new nodes\Star();

        } elseif (!$this->stream->matchesSpecialChar(')')) {
            if ($this->stream->matchesKeyword(['distinct', 'all'])) {
                $distinct = 'distinct' === $this->stream->next()->getValue();
            }
            list($value, $name, $variadic) = $this->GenericFunctionArgument();
            if (!$name) {
                $positionalArguments[] = $value;
            } else {
                $namedArguments[(string)$name] = $value;
            }

            while (!$variadic && $this->stream->matchesSpecialChar(',')) {
                $this->stream->next();

                $argToken = $this->stream->getCurrent();
                list($value, $name, $variadic) = $this->GenericFunctionArgument();
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
            if ($this->stream->matchesKeywordSequence('order', 'by')) {
                $this->stream->skip(2);
                $orderBy = $this->OrderByList();
            }
        }
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        return new nodes\FunctionCall(
            $funcName,
            $positionalArguments instanceof nodes\Star
            ? $positionalArguments : new nodes\lists\FunctionArgumentList($positionalArguments + $namedArguments),
            $distinct,
            $variadic,
            $orderBy
        );
    }

    protected function GenericFunctionName()
    {
        if (
            $this->stream->matches(Token::TYPE_KEYWORD)
            && !$this->stream->matches(Token::TYPE_RESERVED_KEYWORD)
        ) {
            $firstToken = $this->stream->next();
        } else {
            $firstToken = $this->stream->expect(Token::TYPE_IDENTIFIER);
        }
        $funcName = [new nodes\Identifier($firstToken)];

        while ($this->stream->matchesSpecialChar('.')) {
            $this->stream->next();
            if ($this->stream->matches(Token::TYPE_KEYWORD)) {
                $funcName[] = new nodes\Identifier($this->stream->next());
            } else {
                $funcName[] = new nodes\Identifier($this->stream->expect(Token::TYPE_IDENTIFIER));
            }
        }

        if (
            Token::TYPE_TYPE_FUNC_NAME_KEYWORD === $firstToken->getType() && 1 < count($funcName)
            || Token::TYPE_COL_NAME_KEYWORD === $firstToken->getType() && 1 === count($funcName)
        ) {
            throw exceptions\SyntaxException::atPosition(
                implode('.', $funcName) . ' is not a valid function name',
                $this->stream->getSource(),
                $firstToken->getPosition()
            );
        }

        return new nodes\QualifiedName($funcName);
    }

    protected function GenericFunctionArgument()
    {
        if ($variadic = $this->stream->matchesKeyword('variadic')) {
            $this->stream->next();
        }

        $name = null;
        // it's the only place this shit can appear in
        if (
            $this->stream->look(1)->matches(Token::TYPE_COLON_EQUALS)
            || $this->stream->look(1)->matches(Token::TYPE_EQUALS_GREATER)
        ) {
            if ($this->stream->matchesAnyType(Token::TYPE_UNRESERVED_KEYWORD, Token::TYPE_TYPE_FUNC_NAME_KEYWORD)) {
                $name = new nodes\Identifier($this->stream->next());
            } else {
                $name = new nodes\Identifier($this->stream->expect(Token::TYPE_IDENTIFIER));
            }
            $this->stream->next();
        }

        return [$this->Expression(), $name, $variadic];
    }

    protected function ColumnReference()
    {
        $parts = [$this->ColId()];

        $indirection = $this->Indirection();
        while (!empty($indirection) && !($indirection[0] instanceof nodes\ArrayIndexes)) {
            $parts[] = array_shift($indirection);
        }
        if (!empty($indirection)) {
            return new nodes\Indirection($indirection, new nodes\ColumnReference($parts));
        }

        return new nodes\ColumnReference($parts);
    }

    protected function Indirection($allowStar = true)
    {
        $indirection = [];
        while ($this->stream->matchesSpecialChar(['[', '.'])) {
            if ('.' === $this->stream->next()->getValue()) {
                if ($this->stream->matchesSpecialChar('*')) {
                    if (!$allowStar) {
                        // this will basically trigger an error if '.*' appears in list of fields for INSERT or UPDATE
                        $this->stream->expect(Token::TYPE_IDENTIFIER);
                    }
                    $this->stream->next();
                    $indirection[] = new nodes\Star();
                    break;
                } elseif ($this->stream->matches(Token::TYPE_KEYWORD)) {
                    $indirection[] = new nodes\Identifier($this->stream->next());
                } else {
                    $indirection[] = new nodes\Identifier($this->stream->expect(Token::TYPE_IDENTIFIER));
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
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ']');

                $indirection[] = new nodes\ArrayIndexes($lower, $upper, $isSlice);
            }
        }
        return $indirection;
    }

    protected function TargetList()
    {
        $elements = new nodes\lists\TargetList([$this->TargetElement()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $elements[] = $this->TargetElement();
        }

        return $elements;
    }

    protected function TargetElement()
    {
        $alias = null;

        if ($this->stream->matchesSpecialChar('*')) {
            $this->stream->next();
            return new nodes\Star();
        }
        $element = $this->Expression();
        if ($this->stream->matches(Token::TYPE_IDENTIFIER)) {
            $alias = new nodes\Identifier($this->stream->next());

        } elseif ($this->stream->matchesKeyword('as')) {
            $this->stream->next();
            if ($this->stream->matches(Token::TYPE_KEYWORD)) {
                $alias = new nodes\Identifier($this->stream->next());
            } else {
                $alias = new nodes\Identifier($this->stream->expect(Token::TYPE_IDENTIFIER));
            }
        }

        return new nodes\TargetElement($element, $alias);
    }

    protected function FromList()
    {
        $relations = new nodes\lists\FromList([$this->FromElement()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $relations[] = $this->FromElement();
        }

        return $relations;
    }

    /**
     * @return nodes\range\FromElement
     */
    protected function FromElement()
    {
        $left = $this->TableReference();

        while (
            $this->stream->matchesKeyword(['cross', 'natural', 'left', 'right', 'full', 'inner', 'join'])
        ) {
            // CROSS JOIN needs no join quals
            if ('cross' === $this->stream->getCurrent()->getValue()) {
                $this->stream->next();
                $this->stream->expect(Token::TYPE_KEYWORD, 'join');
                $left = new nodes\range\JoinExpression($left, $this->TableReference(), 'cross');
                continue;
            }
            if ('natural' === $this->stream->getCurrent()->getValue()) {
                $this->stream->next();
                $natural = true;
            } else {
                $natural = false;
            }

            if ($this->stream->matchesKeyword('join')) {
                $this->stream->next();
                $joinType = 'inner';
            } else {
                $joinType = $this->stream->expect(Token::TYPE_KEYWORD, ['left', 'right', 'full', 'inner'])
                                ->getValue();
                // noise word
                if ($this->stream->matchesKeyword('outer')) {
                    $this->stream->next();
                }
                $this->stream->expect(Token::TYPE_KEYWORD, 'join');
            }
            $left = new nodes\range\JoinExpression($left, $this->TableReference(), $joinType);

            if ($natural) {
                $left->setNatural(true);

            } else {
                $token = $this->stream->expect(Token::TYPE_KEYWORD, ['on', 'using']);
                if ('on' === $token->getValue()) {
                    $left->setOn($this->Expression());
                } else {
                    $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
                    $using = $this->ColIdList();
                    $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
                    $left->setUsing($using);
                }
            }
        }

        return $left;
    }

    protected function TableReference()
    {
        if ($this->stream->matchesKeyword('lateral')) {
            $this->stream->next();
            // lateral can only apply to subselects, XMLTABLEs or function invocations
            if ($this->stream->matchesSpecialChar('(')) {
                $reference = $this->RangeSubselect();
            } elseif ($this->stream->matchesKeyword('xmltable')) {
                $reference = $this->XmlTable();
            } else {
                $reference = $this->RangeFunctionCall();
            }
            $reference->setLateral(true);

        } elseif ($this->stream->matchesSpecialChar('(')) {
            // parentheses may contain either a subselect or JOIN expression
            if ('select' === $this->checkContentsOfParentheses()) {
                $reference = $this->RangeSubselect();
            } else {
                $this->stream->next();
                $reference = $this->FromElement();
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
                if ($alias = $this->OptionalAliasClause()) {
                    $reference->setAlias($alias[0], $alias[1]);
                }
            }

        } elseif ($this->stream->matchesKeyword('xmltable')) {
            $reference = $this->XmlTable();

        } elseif (
            $this->stream->matchesKeywordSequence('rows', 'from')
                  || $this->matchesFunctionCall()
        ) {
            $reference = $this->RangeFunctionCall();

        } else {
            $reference = $this->RelationExpression();
        }

        return $reference;
    }

    protected function RangeSubselect()
    {
        $token     = $this->stream->getCurrent();
        $reference = new nodes\range\Subselect($this->SelectWithParentheses());

        if (!($alias = $this->OptionalAliasClause())) {
            throw exceptions\SyntaxException::atPosition(
                'Subselects in FROM clause should have an alias',
                $this->stream->getSource(),
                $token->getPosition()
            );
        }
        $reference->setAlias($alias[0], $alias[1]);

        return $reference;
    }

    protected function RangeFunctionCall()
    {
        if ($this->stream->matchesKeywordSequence('rows', 'from')) {
            $this->stream->skip(2);
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
            $list = new nodes\lists\RowsFromList([$this->RowsFromElement()]);
            while ($this->stream->matchesSpecialChar(',')) {
                $this->stream->next();
                $list[] = $this->RowsFromElement();
            }
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

            $reference = new nodes\range\RowsFrom($list);

        } else {
            if (null === ($function = $this->SpecialFunctionCall())) {
                $function = $this->GenericFunctionCall();
            }
            $reference = new nodes\range\FunctionCall($function);
        }

        if ($this->stream->matchesKeywordSequence('with', 'ordinality')) {
            $this->stream->skip(2);
            $reference->setWithOrdinality(true);
        }

        if ($alias = $this->OptionalAliasClause(true)) {
            $reference->setAlias($alias[0], $alias[1]);
        }

        return $reference;
    }

    protected function RowsFromElement()
    {
        if (null === ($function = $this->SpecialFunctionCall())) {
            $function = $this->GenericFunctionCall();
        }

        if (!$this->stream->matchesKeyword('as')) {
            $aliases = null;
        } else {
            $this->stream->next();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
            $aliases = new nodes\lists\ColumnDefinitionList([$this->TableFuncElement()]);
            while ($this->stream->matchesSpecialChar(',')) {
                $this->stream->next();
                $aliases[] = $this->TableFuncElement();
            }
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
        }
        return new nodes\range\RowsFromElement($function, $aliases);
    }

    /**
     * relation_expr_opt_alias from grammar, with no special case for SET
     *
     * Not used in parser itself, needed by StatementFactory
     *
     * @return nodes\range\RelationReference
     */
    protected function RelationExpressionOptAlias()
    {
        return $this->RelationExpression('delete');
    }

    protected function InsertTarget()
    {
        $name  = $this->QualifiedName();
        $alias = null;

        // AS is required for aliases in INSERT
        if ($this->stream->matchesKeyword('as')) {
            $this->stream->next();
            $alias = $this->ColId();
        }

        return new nodes\range\InsertTarget($name, $alias);
    }

    protected function RelationExpression($statementType = 'select')
    {
        $inherit           = null;
        $expectParenthesis = false;
        if ($this->stream->matchesKeyword('only')) {
            $this->stream->next();
            $inherit = false;
            if ($this->stream->matchesSpecialChar('(')) {
                $expectParenthesis = true;
                $this->stream->next();
            }
        }

        $name = $this->QualifiedName();

        if (false === $inherit && $expectParenthesis) {
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        } elseif (null === $inherit && $this->stream->matchesSpecialChar('*')) {
            $this->stream->next();
            $inherit = true;
        }

        if ('select' !== $statementType) {
            $expression = new nodes\range\UpdateOrDeleteTarget(
                $name,
                $this->DMLAliasClause($statementType),
                $inherit
            );
        } else {
            $expression = new nodes\range\RelationReference($name, $inherit);
            if ($alias = $this->OptionalAliasClause()) {
                $expression->setAlias($alias[0], $alias[1]);
            }
            if ($this->stream->matchesKeyword('tablesample')) {
                $this->stream->next();
                $method     = $this->GenericFunctionName();
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
                $arguments  = $this->ExpressionList();
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

                $repeatable = null;
                if ($this->stream->matchesKeyword('repeatable')) {
                    $this->stream->next();
                    $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
                    $repeatable = $this->Expression();
                    $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
                }

                $expression = new nodes\range\TableSample($expression, $method, $arguments, $repeatable);
            }
        }

        return $expression;
    }

    /**
     *
     * Corresponds to relation_expr_opt_alias production from grammar, see the
     * comment there.
     *
     * @param string $statementType
     * @return nodes\Identifier|null
     */
    protected function DMLAliasClause($statementType)
    {
        if (
            $this->stream->matchesKeyword('as')
            || $this->stream->matchesAnyType(Token::TYPE_IDENTIFIER, Token::TYPE_COL_NAME_KEYWORD)
            || ($this->stream->matches(Token::TYPE_UNRESERVED_KEYWORD)
                && ('update' !== $statementType || 'set' !== $this->stream->getCurrent()->getValue()))
        ) {
            if ($this->stream->matchesKeyword('as')) {
                $this->stream->next();
            }
            return $this->ColId();
        }
        return null;
    }

    protected function OptionalAliasClause($functionAlias = false)
    {
        if (
            $this->stream->matchesKeyword('as')
            || $this->stream->matchesAnyType(
                Token::TYPE_IDENTIFIER,
                Token::TYPE_UNRESERVED_KEYWORD,
                Token::TYPE_COL_NAME_KEYWORD
            )
        ) {
            $tableAlias    = null;
            $columnAliases = null;

            // AS is complete noise here, unlike in TargetList
            if ($this->stream->matchesKeyword('as')) {
                $this->stream->next();
            }
            if (!$functionAlias || !$this->stream->matchesSpecialChar('(')) {
                $tableAlias = $this->ColId();
            }
            if (!$tableAlias || $this->stream->matchesSpecialChar('(')) {
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');

                $tableFuncElement = $functionAlias
                                    // for TableFuncElement this position will contain typename
                                    && (!$this->stream->look(1)->matches(Token::TYPE_SPECIAL_CHAR, [')', ','])
                                        || !$tableAlias);

                $columnAliases = $tableFuncElement
                                 ? new nodes\lists\ColumnDefinitionList([$this->TableFuncElement()])
                                 : new nodes\lists\IdentifierList([$this->ColId()]);
                while ($this->stream->matchesSpecialChar(',')) {
                    $this->stream->next();
                    $columnAliases[] = $tableFuncElement ? $this->TableFuncElement() : $this->ColId();
                }

                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            }

            return [$tableAlias, $columnAliases];
        }
        return null;
    }

    protected function TableFuncElement()
    {
        $alias     = $this->ColId();
        $type      = $this->TypeName();
        $collation = null;
        if ($this->stream->matchesKeyword('collate')) {
            $this->stream->next();
            $collation = $this->QualifiedName();
        }

        return new nodes\range\ColumnDefinition($alias, $type, $collation);
    }

    protected function ColIdList()
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
    protected function ColId()
    {
        if ($this->stream->matchesAnyType(Token::TYPE_UNRESERVED_KEYWORD, Token::TYPE_COL_NAME_KEYWORD)) {
            return new nodes\Identifier($this->stream->next());
        } else {
            return new nodes\Identifier($this->stream->expect(Token::TYPE_IDENTIFIER));
        }
    }

    protected function QualifiedName()
    {
        $parts = [$this->ColId()];

        while ($this->stream->matchesSpecialChar('.')) {
            $this->stream->next();
            if ($this->stream->matches(Token::TYPE_KEYWORD)) {
                $parts[] = new nodes\Identifier($this->stream->next());
            } else {
                $parts[] = new nodes\Identifier($this->stream->expect(Token::TYPE_IDENTIFIER));
            }
        }

        return new nodes\QualifiedName($parts);
    }

    protected function OrderByList()
    {
        $items = new nodes\lists\OrderByList([$this->OrderByElement()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $items[] = $this->OrderByElement();
        }

        return $items;
    }

    protected function OrderByElement()
    {
        $expression = $this->Expression();
        $operator   = $direction = $nullsOrder = null;
        if ($this->stream->matchesKeyword(['asc', 'desc', 'using'])) {
            if ('using' === ($direction = $this->stream->next()->getValue())) {
                $operator = $this->Operator(true);
            }
        }
        if ($this->stream->matchesKeyword('nulls')) {
            $this->stream->next();
            $nullsOrder = $this->stream->expect(Token::TYPE_KEYWORD, ['first', 'last'])->getValue();
        }

        return new nodes\OrderByElement($expression, $direction, $nullsOrder, $operator);
    }

    protected function OnConflict()
    {
        $target = $set = $condition = null;
        if ($this->stream->matchesKeywordSequence('on', 'constraint')) {
            $this->stream->skip(2);
            $target = $this->ColId();

        } elseif ($this->stream->matchesSpecialChar('(')) {
            $target = $this->IndexParameters();
        }

        $this->stream->expect(Token::TYPE_KEYWORD, 'do');
        if ('update' === ($action = $this->stream->expect(Token::TYPE_KEYWORD, ['update', 'nothing'])->getValue())) {
            $this->stream->expect(Token::TYPE_KEYWORD, 'set');
            $set = $this->SetClauseList();
            if ($this->stream->matchesKeyword('where')) {
                $this->stream->next();
                $condition = $this->Expression();
            }
        }

        return new nodes\OnConflictClause($action, $target, $set, $condition);
    }

    protected function IndexParameters()
    {
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');

        $items = new nodes\IndexParameters([$this->IndexElement()]);
        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $items[] = $this->IndexElement();
        }

        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        if ($this->stream->matchesKeyword('where')) {
            $this->stream->next();
            $items->where->condition = $this->Expression();
        }

        return $items;
    }


    protected function IndexElement()
    {
        if ($this->stream->matchesSpecialChar('(')) {
            $this->stream->next();
            $expression = $this->Expression();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        } elseif ($this->matchesFunctionCall()) {
            if (null === ($function = $this->SpecialFunctionCall())) {
                $function = $this->GenericFunctionCall();
            }
            $expression = new nodes\expressions\FunctionExpression(
                is_object($function->name) ? clone $function->name : $function->name,
                clone $function->arguments,
                $function->distinct,
                $function->variadic,
                clone $function->order
            );

        } else {
            $expression = $this->ColId();
        }

        $collation = $opClass = $direction = $nullsOrder = null;

        if ($this->stream->matchesKeyword('collate')) {
            $this->stream->next();
            $collation = $this->QualifiedName();
        }

        if (
            $this->stream->matchesAnyType(
                Token::TYPE_IDENTIFIER,
                Token::TYPE_UNRESERVED_KEYWORD,
                Token::TYPE_COL_NAME_KEYWORD
            )
        ) {
            $opClass = $this->QualifiedName();
        }

        if ($this->stream->matchesKeyword(['asc', 'desc'])) {
            $direction = $this->stream->next()->getValue();
        }

        if ($this->stream->matchesKeyword('nulls')) {
            $this->stream->next();
            $nullsOrder = $this->stream->expect(Token::TYPE_KEYWORD, ['first', 'last'])->getValue();
        }

        return new nodes\IndexElement($expression, $collation, $opClass, $direction, $nullsOrder);
    }

    protected function GroupByList()
    {
        $items = new nodes\lists\GroupByList([$this->GroupByElement()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $items[] = $this->GroupByElement();
        }

        return $items;
    }

    protected function GroupByElement()
    {
        if (
            $this->stream->matchesSpecialChar('(')
            && $this->stream->look()->matches(Token::TYPE_SPECIAL_CHAR, ')')
        ) {
            $this->stream->skip(2);
            $element = new nodes\group\EmptyGroupingSet();

        } elseif ($this->stream->matchesKeyword(['cube', 'rollup'])) {
            $type = $this->stream->next()->getValue();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
            $element = new nodes\group\CubeOrRollupClause($this->ExpressionList(), $type);
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        } elseif ($this->stream->matchesKeywordSequence('grouping', 'sets')) {
            $this->stream->skip(2);
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
            $element = new nodes\group\GroupingSetsClause($this->GroupByList());
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        } else {
            $element = $this->Expression();
        }

        return $element;
    }

    protected function XmlExistsArguments()
    {
        $arguments = [$this->ExpressionAtom()];
        // 'by ref' is noise in Postgres
        $this->stream->expect(Token::TYPE_KEYWORD, 'passing');
        if ($this->stream->matchesKeyword('by')) {
            $this->stream->next();
            $this->stream->expect(Token::TYPE_KEYWORD, ['ref', 'value']);
        }
        $arguments[] = $this->ExpressionAtom();
        if ($this->stream->matchesKeyword('by')) {
            $this->stream->next();
            $this->stream->expect(Token::TYPE_KEYWORD, ['ref', 'value']);
        }

        return $arguments;
    }

    protected function XmlTable()
    {
        $this->stream->expect(Token::TYPE_KEYWORD, 'xmltable');
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');

        $namespaces = null;
        if ($this->stream->matchesKeyword('xmlnamespaces')) {
            $this->stream->next();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
            $namespaces = $this->XmlNamespaceList();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ',');
        }
        $doc = $this->XmlExistsArguments();
        $this->stream->expect(Token::TYPE_KEYWORD, 'columns');
        $columns = $this->XmlColumnList();

        $table = new nodes\range\XmlTable($doc[0], $doc[1], $columns, $namespaces);

        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        if ($alias = $this->OptionalAliasClause()) {
            $table->setAlias($alias[0], $alias[1]);
        }

        return $table;
    }

    protected function XmlNamespaceList()
    {
        $items = new nodes\xml\XmlNamespaceList([$this->XmlNamespace()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $items[] = $this->XmlNamespace();
        }

        return $items;
    }

    protected function XmlNamespace()
    {
        // Default namespace is not currently supported, but Postgres accepts the syntax. We do the same.
        if ($this->stream->matchesKeyword('default')) {
            $this->stream->next();
            $value = $this->RestrictedExpression();
            $alias = null;

        } else {
            $value = $this->RestrictedExpression();
            $this->stream->expect(Token::TYPE_KEYWORD, 'as');
            if ($this->stream->matches(Token::TYPE_KEYWORD)) {
                $alias = new nodes\Identifier($this->stream->next());
            } else {
                $alias = new nodes\Identifier($this->stream->expect(Token::TYPE_IDENTIFIER));
            }
        }

        return new nodes\xml\XmlNamespace($value, $alias);
    }

    protected function XmlColumnList()
    {
        $columns = new nodes\xml\XmlColumnList([$this->XmlColumnDefinition()]);

        while ($this->stream->matchesSpecialChar(',')) {
            $this->stream->next();
            $columns[] = $this->XmlColumnDefinition();
        }

        return $columns;
    }

    protected function XmlColumnDefinition()
    {
        $name = $this->ColId();
        $forOrdinality = false;
        $type = $nullable = $default = $path = null;
        if ($this->stream->matchesKeywordSequence('for', 'ordinality')) {
            $this->stream->skip(2);
            $forOrdinality = true;
        } else {
            $type = $this->TypeName();
            // 'path' is for some reason not a keyword in Postgres, so production for xmltable_column_option_el
            // accepts any identifier and then xmltable_column_el basically rejects anything that is not 'path'.
            // We explicitly check for 'path' here instead.
            do {
                if ($this->stream->matches(Token::TYPE_IDENTIFIER, 'path')) {
                    if (null !== $path) {
                        throw exceptions\SyntaxException::atPosition(
                            "only one PATH value per column is allowed",
                            $this->stream->getSource(),
                            $this->stream->getCurrent()->getPosition()
                        );
                    }
                    $this->stream->next();
                    $path = $this->RestrictedExpression();

                } elseif ($this->stream->matchesKeyword('default')) {
                    if (null !== $default) {
                        throw exceptions\SyntaxException::atPosition(
                            "only one DEFAULT value is allowed",
                            $this->stream->getSource(),
                            $this->stream->getCurrent()->getPosition()
                        );
                    }
                    $this->stream->next();
                    $default = $this->RestrictedExpression();

                } elseif ($this->stream->matchesKeyword('null')) {
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
                    $this->stream->matchesKeyword('not')
                          && $this->stream->look(1)->matches(Token::TYPE_KEYWORD, 'null')
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
        }

        return new nodes\xml\XmlColumnDefinition($name, $forOrdinality, $type, $path, $nullable, $default);
    }
}
