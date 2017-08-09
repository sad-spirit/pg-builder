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
 * @copyright 2014-2017 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

namespace sad_spirit\pg_builder;

use sad_spirit\pg_wrapper\MetadataCache;

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
 * @method Statement                     parseStatement($input)
 * @method SelectCommon                  parseSelectStatement($input)
 * @method nodes\lists\ExpressionList    parseExpressionList($input)
 * @method nodes\ScalarExpression        parseExpression($input)
 * @method nodes\lists\TargetList        parseTargetList($input)
 * @method nodes\TargetElement           parseTargetElement($input)
 * @method nodes\lists\FromList          parseFromList($input)
 * @method nodes\range\FromElement       parseFromElement($input)
 * @method nodes\lists\OrderByList       parseOrderByList($input)
 * @method nodes\OrderByElement          parseOrderByElement($input)
 * @method nodes\lists\WindowList        parseWindowList($input)
 * @method nodes\WindowDefinition        parseWindowDefinition($input)
 * @method nodes\LockingElement          parseLockingElement($input)
 * @method nodes\lists\LockList          parseLockingList($input)
 * @method nodes\range\RelationReference parseRelationExpressionOptAlias($input)
 * @method nodes\QualifiedName           parseQualifiedName($input)
 * @method nodes\lists\SetTargetList     parseSetClause($input)
 * @method nodes\SetTargetElement        parseSingleSetClause($input)
 * @method nodes\lists\SetTargetList     parseInsertTargetList($input)
 * @method nodes\SetTargetElement        parseSetTargetElement($input)
 * @method nodes\lists\CtextRowList      parseCtextRowList($input)
 * @method nodes\lists\CtextRow          parseCtextRow($input)
 * @method nodes\ScalarExpression        parseExpressionWithDefault($input)
 * @method nodes\WithClause              parseWithClause($input)
 * @method nodes\CommonTableExpression   parseCommonTableExpression($input)
 * @method nodes\lists\IdentifierList    parseColIdList($input)
 */
class Parser
{
    /**
     * Use operator precedence for PostgreSQL releases before 9.5
     */
    const OPERATOR_PRECEDENCE_PRE_9_5 = 'pre-9.5';

    /**
     * Use operator precedence for PostgreSQL 9.5 and up
     */
    const OPERATOR_PRECEDENCE_CURRENT = 'current';

    /**
     * mathOp production from grammar
     * @var array
     */
    protected static $mathOp = array('+', '-', '*', '/', '%', '^', '<', '>', '=', '<=', '>=', '!=', '<>');

    /**
     * sub_type production from grammar
     * @var array
     */
    protected static $subType = array('any', 'all', 'some');

    /**
     * Methods that are exposed through __call()
     * @var array
     */
    protected static $callable = array(
        'statement'                  => true,
        'selectstatement'            => true,
        'expression'                 => true,
        'expressionlist'             => true,
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
        'qualifiedname'              => true,
        'setclause'                  => true, // for UPDATE
        'singlesetclause'            => true, // for UPDATE
        'settargetelement'           => true, // for INSERT
        'inserttargetlist'           => true, // for INSERT
        'ctextrowlist'               => true,
        'ctextrow'                   => true,
        'expressionwithdefault'      => true,
        'withclause'                 => true,
        'commontableexpression'      => true,
        'colidlist'                  => true
    );

    /**
     * @var Lexer
     */
    private $_lexer;

    /**
     * @var MetadataCache
     */
    private $_cache;

    /**
     * @var TokenStream
     */
    protected $stream;

    /**
     * @var string
     */
    protected $precedence = self::OPERATOR_PRECEDENCE_CURRENT;

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
    private function _checkContentsOfParentheses()
    {
        $openParens = array();
        $lookIdx    = 0;
        while ($this->stream->look($lookIdx)->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
            array_push($openParens, $lookIdx++);
        }
        if (!$lookIdx) {
            return null;
        }

        if ($this->stream->look($lookIdx)->matches(Token::TYPE_KEYWORD, array('values', 'select', 'with'))) {
            if (1 === ($selectLevel = count($openParens))) {
                return 'select';
            }
        } else {
            $selectLevel = false;
        }

        do {
            $token = $this->stream->look(++$lookIdx);
            if ($token->matches(Token::TYPE_SPECIAL_CHAR, '[')) {
                $lookIdx = $this->_skipParentheses($lookIdx, true) - 1;

            } elseif ($token->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
                array_push($openParens, $lookIdx);

            } elseif ($token->matches(Token::TYPE_SPECIAL_CHAR, ',') && 1 === count($openParens) && !$selectLevel) {
                return 'row';

            } elseif ($token->matches(Token::TYPE_SPECIAL_CHAR, ')')) {
                if (1 < count($openParens) && $selectLevel === count($openParens)) {
                    if ($this->stream->look($lookIdx + 1)->matches(
                        array(')', 'union', 'intersect', 'except', 'order',
                              'limit', 'offset', 'for' /* ...update */, 'fetch' /* SQL:2008 limit */)
                    )) {
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
                "Unbalanced '('", $this->stream->getSource(), $token->getPosition()
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
    private function _skipParentheses($start, $square = false)
    {
        $lookIdx    = $start;
        $openParens = 1;

        do {
            $token = $this->stream->look(++$lookIdx);
            if ($token->matches(Token::TYPE_SPECIAL_CHAR, $square ? '[' : '(')) {
                $openParens++;
            } elseif ($token->matches(Token::TYPE_SPECIAL_CHAR, $square ? ']' : ')')) {
                $openParens--;
            }
        } while ($openParens > 0 && !$token->matches(Token::TYPE_EOF));

        if (0 !== $openParens) {
            $token = $this->stream->look($start);
            throw exceptions\SyntaxException::atPosition(
                "Unbalanced '" . ($square ? '[' : '(') . "'", $this->stream->getSource(), $token->getPosition()
            );
        }

        return $lookIdx + 1;
    }


    /**
     * Tests whether current position of stream matches a (possibly schema-qualified) operator
     *
     * @return bool
     */
    private function _matchesOperator()
    {
        return $this->stream->matches(Token::TYPE_OPERATOR)
               || self::OPERATOR_PRECEDENCE_PRE_9_5 === $this->precedence
                  && $this->stream->matches(Token::TYPE_INEQUALITY)
               || $this->stream->matches(Token::TYPE_KEYWORD, 'operator')
                  && $this->stream->look(1)->matches(Token::TYPE_SPECIAL_CHAR, '(');
    }


    /**
     * Tests whether current position of stream matches 'func_name' production from PostgreSQL's grammar
     *
     * Actually func_name allows indirection via array subscripts and appearance of '*' in
     * name, these are only disallowed later in processing, we disallow these here.
     *
     * @return bool|int position after func_name if matches, false if not
     */
    private function _matchesFuncName()
    {
        if (!$this->stream->matches(Token::TYPE_IDENTIFIER)
            && (!$this->stream->matches(Token::TYPE_KEYWORD)
                || $this->stream->matches(Token::TYPE_RESERVED_KEYWORD))
        ) {
            return false;
        }

        $first = $this->stream->getCurrent();
        $idx   = 0;
        while ($this->stream->look($idx + 1)->matches(Token::TYPE_SPECIAL_CHAR, '.')
               && ($this->stream->look($idx + 2)->matches(Token::TYPE_IDENTIFIER)
                   || $this->stream->look($idx + 2)->matches(Token::TYPE_KEYWORD))
        ) {
            $idx += 2;
        }
        if (Token::TYPE_TYPE_FUNC_NAME_KEYWORD === $first->getType() && 1 < $idx
            || Token::TYPE_COL_NAME_KEYWORD === $first->getType() && 1 === $idx
        ) {
            // does not match func_name production
            return false;
        }

        return $idx + 1;
    }

    /**
     * Tests whether current position of stream matches a function call
     *
     * @return bool
     */
    private function _matchesFunctionCall()
    {
        static $noParens = array(
            'current_date', 'current_time', 'current_timestamp', 'localtime', 'localtimestamp',
            'current_role', 'current_user', 'session_user', 'user', 'current_catalog', 'current_schema'
        );
        static $parens = array(
            'cast', 'extract', 'overlay', 'position', 'substring', 'treat', 'trim', 'nullif', 'coalesce',
            'greatest', 'least', 'xmlconcat', 'xmlelement', 'xmlexists', 'xmlforest', 'xmlparse',
            'xmlpi', 'xmlroot', 'xmlserialize'
        );

        if ($this->stream->matches(Token::TYPE_KEYWORD, $noParens) // function-like stuff that doesn't need parentheses
            || ($this->stream->matches(Token::TYPE_KEYWORD, $parens) // known functions that require parentheses
                && $this->stream->look(1)->matches(Token::TYPE_SPECIAL_CHAR, '('))
            || ($this->stream->matches(Token::TYPE_KEYWORD, 'collation') // COLLATION FOR (...)
                && $this->stream->look(1)->matches(Token::TYPE_KEYWORD, 'for')
                && $this->stream->look(2)->matches(Token::TYPE_SPECIAL_CHAR, '('))
        ) {
            return true;
        }

        // generic function name
        return false !== ($idx = $this->_matchesFuncName())
               && $this->stream->look($idx)->matches(Token::TYPE_SPECIAL_CHAR, '(');
    }

    /**
     * Tests whether current position of stream looks like a start of Expression()
     *
     * Used to decide whether a custom operator should be infix or postfix one, the
     * former having higher precedence.
     *
     * @return bool
     */
    private function _matchesExpressionStart()
    {
        return (!$this->stream->matches(Token::TYPE_RESERVED_KEYWORD) && !$this->stream->matches(Token::TYPE_SPECIAL))
               || $this->stream->matches(Token::TYPE_KEYWORD, array('not', 'true', 'false', 'null', 'row', 'array', 'case', 'exists'))
               || $this->stream->matches(Token::TYPE_SPECIAL_CHAR, array('(', '+', '-'))
               || $this->stream->matches(Token::TYPE_OPERATOR)
               || $this->_matchesFunctionCall();
    }

    /**
     * Tests whether current position of stream looks like a type cast with standard type name: typename 'string constant'
     *
     * @return bool
     */
    private function _matchesConstTypecast()
    {
        static $constNames = array(
            'int', 'integer', 'smallint', 'bigint', 'real', 'float', 'decimal', 'dec', 'numeric', 'boolean',
            'double', 'bit', 'national', 'character', 'char', 'varchar', 'nchar', 'time', 'timestamp', 'interval'
        );
        static $requiredSequence = array(
            'double'   => 'precision',
            'national' => array('character', 'char')
        );
        static $optVarying = array(
            'bit', 'character', 'char', 'nchar', 'national'
        );
        static $noModifiers = array(
            'int', 'integer', 'smallint', 'bigint', 'real', 'boolean', 'double'
        );
        static $trailingTimezone = array(
            'time', 'timestamp'
        );
        static $trailingCharset = array(
            'character', 'char', 'varchar', 'nchar'
        );

        if (!$this->stream->matches(Token::TYPE_KEYWORD, $constNames)) {
            return false;
        }
        $base = $this->stream->getCurrent()->getValue();
        $idx  = 1;
        if (isset($requiredSequence[$base])
            && !$this->stream->look($idx++)->matches(Token::TYPE_KEYWORD, $requiredSequence[$base])
        ) {
            return false;
        }
        if (in_array($base, $optVarying) && $this->stream->look($idx)->matches(Token::TYPE_KEYWORD, 'varying')) {
            $idx++;
        }
        if (!in_array($base, $noModifiers)
            && $this->stream->look($idx)->matches(Token::TYPE_SPECIAL_CHAR, '(')
        ) {
            $idx = $this->_skipParentheses($idx);
        }
        if (in_array($base, $trailingTimezone)) {
            if ($this->stream->look($idx)->matches(Token::TYPE_KEYWORD, array('with', 'without'))) {
                $idx += 3;
            }
        } elseif (in_array($base, $trailingCharset)) {
            if ($this->stream->look($idx)->matches(Token::TYPE_KEYWORD, 'character')) {
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
    private function _matchesTypecast()
    {
        if ($this->_matchesConstTypecast()) {
            return true;

        } elseif (false === ($idx = $this->_matchesFuncName())) {
            return false;
        }

        if ($this->stream->look($idx)->matches(Token::TYPE_SPECIAL_CHAR, '(')
            && !$this->stream->look($idx + 1)->matches(Token::TYPE_SPECIAL_CHAR, ')')
        ) {
            $idx = $this->_skipParentheses($idx);
        }

        return $this->stream->look($idx)->matches(Token::TYPE_STRING);
    }

    /**
     * Constructor, sets Lexer and Cache implementations to use
     *
     * It is recommended to always use cache in production: parsing is slow.
     *
     * @param Lexer         $lexer
     * @param MetadataCache $cache
     */
    public function __construct(Lexer $lexer, MetadataCache $cache = null)
    {
        $this->_lexer = $lexer;
        $this->_cache = $cache;
    }

    /**
     * Sets operator precedence to use when parsing expressions
     *
     * PostgreSQL 9.5 changed operator precedence to better follow SQL standard:
     *  - The precedence of <=, >= and <> has been reduced to match that of <, > and =.
     *  - The precedence of IS tests (e.g., x IS NULL) has been reduced to be just below these six
     *    comparison operators.
     *  - Also, multi-keyword operators beginning with NOT now have the precedence of their base operator
     *    (for example, NOT BETWEEN now has the same precedence as BETWEEN) whereas before they had
     *    inconsistent precedence, behaving like NOT with respect to their left operand but like
     *    their base operator with respect to their right operand.
     *
     * This setting allows switching between pre-9.5 and 9.5+ operator precedence.
     * Setting precedence to pre-9.5 will also allow using '=>' as custom operator
     * and make equality operator right-associative so that
     * <code>
     * select true = true = true;
     * </code>
     * will parse.
     *
     * Note that even "pre 9.5" setting will not reproduce the buggy behaviour of
     * "not whatever" constructs with left operands, e.g. expression
     * <code>
     * select true = 'foo' not like 'bar';
     * </code>
     * will parse properly using either precedence setting.
     *
     * @param string $precedence
     */
    public function setOperatorPrecedence($precedence)
    {
        if (self::OPERATOR_PRECEDENCE_PRE_9_5 !== $precedence) {
            $precedence = self::OPERATOR_PRECEDENCE_CURRENT;
        }
        $this->precedence = $precedence;
    }

    /**
     * Returns operator precedence used by the parser
     *
     * @return string
     */
    public function getOperatorPrecedence()
    {
        return $this->precedence;
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
        if (!preg_match('/^parse([a-zA-Z]+)$/', $name, $matches)
            || !isset(self::$callable[strtolower($matches[1])])
        ) {
            throw new exceptions\BadMethodCallException("The method '{$name}' is not available");
        }

        $cacheKey = null;
        if ($this->_cache) {
            $cacheKey = 'parsetree-' . md5("{$name}" . $arguments[0]);
            if (null !== ($cached = $this->_cache->getItem($cacheKey))) {
                return $cached;
            }
        }

        if ($arguments[0] instanceof TokenStream) {
            $this->stream = $arguments[0];
        } else {
            $this->stream = $this->_lexer->tokenize($arguments[0]);
        }

        $parsed = call_user_func(array($this, $matches[1]));

        // Can't use expect here: it will try to move stream position beyond the end
        if (!$this->stream->matches(Token::TYPE_EOF)) {
            throw exceptions\SyntaxException::expectationFailed(
                Token::TYPE_EOF, null, $this->stream->getCurrent(), $this->stream->getSource()
            );
        }

        if ($cacheKey) {
            $this->_cache->setItem($cacheKey, $parsed);
        }

        return $parsed;
    }

    protected function Statement()
    {
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'with')) {
            $withClause = $this->WithClause();
        }

        if ($this->stream->matches(Token::TYPE_KEYWORD, array('select', 'values'))
            || $this->stream->matches(Token::TYPE_SPECIAL_CHAR, '(')
        ) {
            $stmt = $this->SelectStatement();
            if (!empty($withClause)) {
                if (0 < count($stmt->with)) {
                    throw new exceptions\SyntaxException('Multiple WITH clauses are not allowed');
                }
                $stmt->with = $withClause;
            }
            return $stmt;

        } elseif ($this->stream->matches(Token::TYPE_KEYWORD, 'insert')) {
            $stmt = $this->InsertStatement();

        } elseif ($this->stream->matches(Token::TYPE_KEYWORD, 'update')) {
            $stmt = $this->UpdateStatement();

        } elseif ($this->stream->matches(Token::TYPE_KEYWORD, 'delete')) {
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
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'with')) {
            $withClause = $this->WithClause();
        }

        $stmt = $this->SelectIntersect();

        while ($this->stream->matches(Token::TYPE_KEYWORD, array('union', 'except'))) {
            $setOp = $this->stream->next()->getValue();
            if ($this->stream->matches(Token::TYPE_KEYWORD, array('all', 'distinct'))) {
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
        if ($this->stream->matchesSequence(array('order', 'by'))) {
            if (count($stmt->order) > 0) {
                throw exceptions\SyntaxException::atPosition(
                    'Multiple ORDER BY clauses are not allowed', $this->stream->getSource(),
                    $this->stream->getCurrent()->getPosition()
                );
            }
            $this->stream->skip(2);
            $stmt->order->merge($this->OrderByList());
        }

        // LIMIT / OFFSET clause and FOR [UPDATE] clause may come in any order
        if ($this->stream->matches(Token::TYPE_KEYWORD, array('for', 'limit', 'offset', 'fetch'))) {
            if ('for' === $this->stream->getCurrent()->getValue()) {
                // locking clause first
                $this->ForLockingClause($stmt);
                if ($this->stream->matches(Token::TYPE_KEYWORD, array('limit', 'offset', 'fetch'))) {
                    $this->LimitOffsetClause($stmt);
                }

            } else {
                // limit clause first
                $this->LimitOffsetClause($stmt);
                if ($this->stream->matches(Token::TYPE_KEYWORD, 'for')) {
                    $this->ForLockingClause($stmt);
                }
            }
        }

        return $stmt;
    }

    protected function InsertStatement()
    {
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'with')) {
            $withClause = $this->WithClause();
        }
        $this->stream->expect(Token::TYPE_KEYWORD, 'insert');
        $this->stream->expect(Token::TYPE_KEYWORD, 'into');

        $stmt = new Insert($this->QualifiedName());
        if (!empty($withClause)) {
            $stmt->with = $withClause;
        }

        if ($this->stream->matchesSequence(array('default', 'values'))) {
            $this->stream->skip(2);
        } else {
            if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '(')
                && 'select' !== $this->_checkContentsOfParentheses()
            ) {
                $this->stream->next();
                $cols = $this->InsertTargetList();
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
                $stmt->cols->merge($cols);
            }
            $stmt->values = $this->SelectStatement();
        }

        if ($this->stream->matches(Token::TYPE_KEYWORD, 'returning')) {
            $this->stream->next();
            $stmt->returning->merge($this->TargetList());
        }

        return $stmt;
    }

    /**
     * @return Update
     * @throws exceptions\NotImplementedException
     */
    protected function UpdateStatement()
    {
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'with')) {
            $withClause = $this->WithClause();
        }

        $this->stream->expect(Token::TYPE_KEYWORD, 'update');
        $relation = $this->RelationExpression('update');
        $this->stream->expect(Token::TYPE_KEYWORD, 'set');

        $stmt = new Update($relation, $this->SetClause());

        if (!empty($withClause)) {
            $stmt->with = $withClause;
        }

        if ($this->stream->matches(Token::TYPE_KEYWORD, 'from')) {
            $this->stream->next();
            $stmt->from->merge($this->FromList());
        }
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'where')) {
            $this->stream->next();
            if ($this->stream->matchesSequence(array('current', 'of'))) {
                throw new exceptions\NotImplementedException('WHERE CURRENT OF clause is not supported');
            }
            $stmt->where->condition = $this->Expression();
        }
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'returning')) {
            $this->stream->next();
            $stmt->returning->merge($this->TargetList());
        }

        return $stmt;
    }

    /**
     * @return Delete
     * @throws exceptions\NotImplementedException
     */
    protected function DeleteStatement()
    {
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'with')) {
            $withClause = $this->WithClause();
        }
        $this->stream->expect(Token::TYPE_KEYWORD, 'delete');
        $this->stream->expect(Token::TYPE_KEYWORD, 'from');

        $stmt = new Delete($this->RelationExpression('delete'));

        if (!empty($withClause)) {
            $stmt->with = $withClause;
        }

        if ($this->stream->matches(Token::TYPE_KEYWORD, 'using')) {
            $this->stream->next();
            $stmt->using->merge($this->FromList());
        }
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'where')) {
            $this->stream->next();
            if ($this->stream->matchesSequence(array('current', 'of'))) {
                throw new exceptions\NotImplementedException('WHERE CURRENT OF clause is not supported');
            }
            $stmt->where->condition = $this->Expression();
        }
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'returning')) {
            $this->stream->next();
            $stmt->returning->merge($this->TargetList());
        }

        return $stmt;
    }

    protected function WithClause()
    {
        $this->stream->expect(Token::TYPE_KEYWORD, 'with');
        if ($recursive = $this->stream->matches(Token::TYPE_KEYWORD, 'recursive')) {
            $this->stream->next();
        }

        $ctes = array($this->CommonTableExpression());
        while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
            $this->stream->next();
            $ctes[] = $this->CommonTableExpression();
        }

        return new nodes\WithClause($ctes, $recursive);
    }

    protected function CommonTableExpression()
    {
        $alias         = $this->ColId();
        $columnAliases = new nodes\lists\IdentifierList();
        if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
            do {
                $this->stream->next();
                $columnAliases[] = $this->ColId();
            } while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ','));
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
        }
        $this->stream->expect(Token::TYPE_KEYWORD, 'as');
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
        $statement = $this->Statement();
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        return new nodes\CommonTableExpression($statement, $alias, $columnAliases);
    }

    protected function ForLockingClause(SelectCommon $stmt)
    {
        if ($this->stream->matchesSequence(array('for', 'read', 'only'))) {
            // this isn't quite documented but means "no locking" judging by the grammar
            $this->stream->skip(3);
            return;
        }

        if ($stmt instanceof Values) {
            throw exceptions\SyntaxException::atPosition(
                'SELECT FOR UPDATE/SHARE cannot be applied to VALUES',
                $this->stream->getSource(), $this->stream->getCurrent()->getPosition()
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
        } while ($this->stream->matches(Token::TYPE_KEYWORD, 'for'));

        return $list;
    }

    protected function LockingElement()
    {
        $this->stream->expect(Token::TYPE_KEYWORD, 'for');
        switch ($strength = $this->stream->expect(Token::TYPE_KEYWORD, array('update', 'no', 'share', 'key'))
                    ->getValue()
        ) {
        case 'no':
            $strength .= ' ' . $this->stream->expect(Token::TYPE_KEYWORD, 'key')->getValue()
                         . ' ' . $this->stream->expect(Token::TYPE_KEYWORD, 'update')->getValue();
            break;
        case 'key':
            $strength .= ' ' . $this->stream->expect(Token::TYPE_KEYWORD, 'share')->getValue();
        }

        $relations = array();
        $noWait    = false;

        if ($this->stream->matches(Token::TYPE_KEYWORD, 'of')) {
            do {
                $this->stream->next();
                $relations[] = $this->QualifiedName();
            } while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ','));
        }

        if ($this->stream->matches(Token::TYPE_KEYWORD, 'nowait')) {
            $this->stream->next();
            $noWait = true;
        }

        return new nodes\LockingElement($strength, $relations, $noWait);
    }

    protected function LimitOffsetClause(SelectCommon $stmt)
    {
        // LIMIT and OFFSET clauses may come in any order
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'offset')) {
            $this->OffsetClause($stmt);
            if ($this->stream->matches(Token::TYPE_KEYWORD, array('limit', 'fetch'))) {
                $this->LimitClause($stmt);
            }
        } else {
            $this->LimitClause($stmt);
            if ($this->stream->matches(Token::TYPE_KEYWORD, 'offset')) {
                $this->OffsetClause($stmt);
            }
        }
    }

    protected function LimitClause(SelectCommon $stmt)
    {
        if ($stmt->limit) {
            throw exceptions\SyntaxException::atPosition(
                'Multiple LIMIT clauses are not allowed',
                $this->stream->getSource(), $this->stream->getCurrent()->getPosition()
            );
        }
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'limit')) {
            // Traditional Postgres LIMIT clause
            $this->stream->next();
            if ($this->stream->matches(Token::TYPE_KEYWORD, 'all')) {
                $this->stream->next();
                $stmt->limit = new nodes\Constant(null);
            } else {
                $stmt->limit = $this->Expression();
            }

        } else {
            // SQL:2008 syntax
            $this->stream->expect(Token::TYPE_KEYWORD, 'fetch');
            $this->stream->expect(Token::TYPE_KEYWORD, array('first', 'next'));

            if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
                $this->stream->next();
                $stmt->limit = $this->Expression();
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

            } elseif ($this->stream->matches(Token::TYPE_KEYWORD, array('row', 'rows'))) {
                // no limit specified -> 1 row
                $stmt->limit = new nodes\Constant(1);

            } else {
                // Postgres won't allow a negative limit anyway
                if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '+')) {
                    $this->stream->next();
                }
                $stmt->limit = new nodes\Constant($this->stream->expect(Token::TYPE_INTEGER));
            }

            $this->stream->expect(Token::TYPE_KEYWORD, array('row', 'rows'));
            $this->stream->expect(Token::TYPE_KEYWORD, 'only');
        }
    }

    protected function OffsetClause(SelectCommon $stmt)
    {
        if ($stmt->offset) {
            throw exceptions\SyntaxException::atPosition(
                'Multiple OFFSET clauses are not allowed',
                $this->stream->getSource(), $this->stream->getCurrent()->getPosition()
            );
        }
        // NB: the following is a bit different from actual Postgres grammar, where offset only
        // allows c_expr (i.e. ExpressionAtom) production if trailed by ROW / ROWS, but full
        // a_expr (i.e. Expression) production in other case. We don't bother to do lookahead
        // here, so allow Expression in either case and treat trailing ROW / ROWS as noise
        $this->stream->expect(Token::TYPE_KEYWORD, 'offset');
        $stmt->offset = $this->Expression();
        if ($this->stream->matches(Token::TYPE_KEYWORD, array('row', 'rows'))) {
            $this->stream->next();
        }
    }

    protected function SetClause()
    {
        if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
            $targetList = $this->MultipleSetClause();
        } else {
            $targetList = new nodes\lists\SetTargetList(array($this->SingleSetClause()));
        }
        while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
            $this->stream->next();
            if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
                $targetList->merge($this->MultipleSetClause());
            } else {
                $targetList[] = $this->SingleSetClause();
            }
        }
        return $targetList;
    }

    protected function MultipleSetClause()
    {
        $first   = $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
        $columns = new nodes\lists\SetTargetList(array($this->SetTargetElement()));
        while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
            $this->stream->next();
            $columns[] = $this->SetTargetElement();
        }
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '=');

        $values = $this->CtextRow();
        if (count($columns) != count($values)) {
            throw exceptions\SyntaxException::atPosition(
                'Number of columns does not match number of values',
                $this->stream->getSource(), $first->getPosition()
            );
        }

        /* @var $columns nodes\SetTargetElement[] */
        for ($i = 0; $i < count($columns); $i++) {
            $columns[$i]->setValue($values[$i]);
        }

        return $columns;
    }

    protected function CtextRowList()
    {
        $list = new nodes\lists\CtextRowList(array($this->CtextRow()));
        while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
            $this->stream->next();
            $list[] = $this->CtextRow();
        }
        return $list;
    }

    protected function CtextRow()
    {
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
        $values = array($this->ExpressionWithDefault());
        while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
            $this->stream->next();
            $values[] = $this->ExpressionWithDefault();
        }
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        return $values;
    }

    protected function SingleSetClause()
    {
        $target = $this->SetTargetElement();
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '=');
        $target->setValue($this->ExpressionWithDefault());

        return $target;
    }

    protected function InsertTargetList()
    {
        $list = new nodes\lists\SetTargetList(array($this->SetTargetElement()));
        while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
            $this->stream->next();
            $list[] = $this->SetTargetElement();
        }
        return $list;
    }

    protected function SetTargetElement()
    {
        return new nodes\SetTargetElement($this->ColId(), $this->Indirection(false));
    }

    protected function ExpressionWithDefault()
    {
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'default')) {
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

        while ($this->stream->matches(Token::TYPE_KEYWORD, 'intersect')) {
            $setOp = $this->stream->next()->getValue();
            if ($this->stream->matches(Token::TYPE_KEYWORD, array('all', 'distinct'))) {
                $setOp .= ('all' === $this->stream->next()->getValue() ? ' all' : '');
            }
            $stmt = new SetOpSelect($stmt, $this->SimpleSelect(), $setOp);
        }

        return $stmt;
    }

    protected function SimpleSelect()
    {
        if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
            return $this->SelectWithParentheses(); // select_with_parens grammar production
        }

        $token = $this->stream->expect(Token::TYPE_KEYWORD, array('select', 'values'));
        if ('values' === $token->getValue()) {
            return new Values($this->CtextRowList());
        }

        $distinctClause = false;

        if ($this->stream->matches(Token::TYPE_KEYWORD, 'all')) {
            // noise "ALL"
            $this->stream->next();
        } elseif ($this->stream->matches(Token::TYPE_KEYWORD, 'distinct')) {
            $this->stream->next();
            if (!$this->stream->matches(Token::TYPE_KEYWORD, 'on')) {
                $distinctClause = true;
            } else {
                $this->stream->next();
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
                $distinctClause = $this->ExpressionList();
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            }
        }

        $stmt = new Select($this->TargetList(), $distinctClause);

        if ($this->stream->matches(Token::TYPE_KEYWORD, 'into')) {
            throw new exceptions\NotImplementedException("SELECT INTO clauses are not supported");
        }

        if ($this->stream->matches(Token::TYPE_KEYWORD, 'from')) {
            $this->stream->next();
            $stmt->from->merge($this->FromList());
        }

        if ($this->stream->matches(Token::TYPE_KEYWORD, 'where')) {
            $this->stream->next();
            $stmt->where->condition = $this->Expression();
        }

        if ($this->stream->matchesSequence(array('group', 'by'))) {
            $this->stream->skip(2);
            $stmt->group->merge($this->ExpressionList());
        }

        if ($this->stream->matches(Token::TYPE_KEYWORD, 'having')) {
            $this->stream->next();
            $stmt->having->condition = $this->Expression();
        }

        if ($this->stream->matches(Token::TYPE_KEYWORD, 'window')) {
            $this->stream->next();
            $stmt->window->merge($this->WindowList());
        }

        return $stmt;
    }

    protected function WindowList()
    {
        $windows = new nodes\lists\WindowList(array($this->WindowDefinition()));

        while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
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
        $refName = $partition = $frameType = $start = $end = $order = null;
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
        if ($this->stream->matches(Token::TYPE_IDENTIFIER)
            || ($this->stream->matches(Token::TYPE_UNRESERVED_KEYWORD)
                // See comment for opt_existing_window_name production in gram.y
                && !in_array($this->stream->getCurrent()->getValue(), array('partition', 'range', 'rows')))
            || $this->stream->matches(Token::TYPE_COL_NAME_KEYWORD)
        ) {
            $refName = $this->ColId();
        }
        if ($this->stream->matchesSequence(array('partition', 'by'))) {
            $this->stream->skip(2);
            $partition = $this->ExpressionList();
        }
        if ($this->stream->matchesSequence(array('order', 'by'))) {
            $this->stream->skip(2);
            $order = $this->OrderByList();
        }
        if ($this->stream->matches(Token::TYPE_KEYWORD, array('range', 'rows'))) {
            $frameType  = $this->stream->next();
            $tokenStart = $this->stream->getCurrent();
            if (!$this->stream->matches(Token::TYPE_KEYWORD, 'between')) {
                $start = $this->WindowFrameBound();
                if ('following' === $start->direction) {
                    // like in frame_extent production in gram.y, reject invalid frame cases
                    if (!$start->value) {
                        throw exceptions\SyntaxException::atPosition(
                            'Frame start cannot be UNBOUNDED FOLLOWING',
                            $this->stream->getSource(), $tokenStart->getPosition()
                        );
                    } else {
                        throw exceptions\SyntaxException::atPosition(
                            'Frame starting from following row cannot end with current row',
                            $this->stream->getSource(), $tokenStart->getPosition()
                        );
                    }
                }

            } else {
                $this->stream->next();
                $start    = $this->WindowFrameBound();
                $this->stream->expect(Token::TYPE_KEYWORD, 'and');
                $tokenEnd = $this->stream->getCurrent();
                $end      = $this->WindowFrameBound();
                // like in frame_extent production in gram.y, reject invalid frame cases
                if ('following' === $start->direction && !$start->value) {
                    throw exceptions\SyntaxException::atPosition(
                        'Frame start cannot be UNBOUNDED FOLLOWING',
                        $this->stream->getSource(), $tokenStart->getPosition()
                    );
                }
                if ('preceding' === $end->direction && !$end->value) {
                    throw exceptions\SyntaxException::atPosition(
                        "Frame end cannot be UNBOUNDED PRECEDING",
                        $this->stream->getSource(), $tokenEnd->getPosition()
                    );
                }
                if ('current row' === $start->direction && 'preceding' === $end->direction) {
                    throw exceptions\SyntaxException::atPosition(
                        "Frame starting from current row cannot have preceding rows",
                        $this->stream->getSource(), $tokenEnd->getPosition()
                    );
                }
                if ('following' === $start->direction && in_array($end->direction, array('current row', 'preceding'))) {
                    throw exceptions\SyntaxException::atPosition(
                        "Frame starting from following row cannot have preceding rows",
                        $this->stream->getSource(), $tokenEnd->getPosition()
                    );
                }
            }
            // like in opt_frame_clause production in gram.y, reject invalid frame cases
            if ('range' === $frameType->getValue()
                && ($start->value || isset($end) && $end->value)
            ) {
                $boundName = strtoupper($start->value ? $start->direction : $end->direction);
                throw exceptions\SyntaxException::atPosition(
                    "RANGE {$boundName} is only supported with UNBOUNDED",
                    $this->stream->getSource(), $frameType->getPosition()
                );
            }
        }

        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        return new nodes\WindowDefinition(
            $refName, $partition, $order, $frameType ? $frameType->getValue() : null, $start, $end
        );
    }

    protected function WindowFrameBound()
    {
        static $checks = array(
            array('unbounded', 'preceding'),
            array('unbounded', 'following'),
            array('current', 'row')
        );

        foreach ($checks as $check) {
            if ($this->stream->matchesSequence($check)) {
                $this->stream->skip(2);
                return new nodes\WindowFrameBound('current' === $check[0] ? 'current row' : $check[1]);
            }
        }

        $value     = $this->Expression();
        $direction = $this->stream->expect(Token::TYPE_KEYWORD, array('preceding', 'following'))->getValue();
        return new nodes\WindowFrameBound($direction, $value);
    }

    protected function ExpressionList()
    {
        $expressions = new nodes\lists\ExpressionList(array($this->Expression()));

        while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
            $this->stream->next();
            $expressions[] = $this->Expression();
        }

        return $expressions;
    }

    protected function Expression()
    {
        $terms = array($this->LogicalExpressionTerm());

        while ($this->stream->matches(Token::TYPE_KEYWORD, 'or')) {
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
        $factors = array($this->LogicalExpressionFactor());

        while ($this->stream->matches(Token::TYPE_KEYWORD, 'and')) {
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
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'not')) {
            $this->stream->next();
            return new nodes\expressions\OperatorExpression('not', null, $this->LogicalExpressionFactor());
        }
        return self::OPERATOR_PRECEDENCE_PRE_9_5 === $this->precedence
               ? $this->ComparisonEquality()
               : $this->IsWhateverExpression();
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

        if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, array('<', '>', '='))
            || $this->stream->matches(Token::TYPE_INEQUALITY)
        ) {
            return new nodes\expressions\OperatorExpression(
                $this->stream->next()->getValue(), $argument,
                $restricted ? $this->GenericOperatorExpression(true) : $this->PatternMatchingExpression()
            );
        }

        return $argument;
    }

    protected function ComparisonEquality($restricted = false)
    {
        $term = $this->ComparisonInEquality($restricted);
        if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '=')) {
            $this->stream->next();
            return new nodes\expressions\OperatorExpression('=', $term, $this->ComparisonEquality($restricted));
        }
        return $term;
    }

    protected function ComparisonInEquality($restricted = false)
    {
        $argument = $restricted
                    ? $this->GenericOperatorExpression(true)
                    : $this->PatternMatchingExpression();

        if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, array('<', '>'))) {
            return new nodes\expressions\OperatorExpression(
                $this->stream->next()->getValue(), $argument,
                $restricted ? $this->GenericOperatorExpression(true) : $this->PatternMatchingExpression()
            );
        }

        return $argument;
    }

    protected function PatternMatchingExpression()
    {
        static $checks = array(
            array('like'),
            array('not', 'like'),
            array('ilike'),
            array('not', 'ilike'),
            // the following cannot be applied to subquery operators
            array('similar', 'to'),
            array('not', 'similar', 'to')
        );

        $string = $this->OverlapsExpression();

        // speedup
        if (!$this->stream->matches(Token::TYPE_KEYWORD, array('like', 'ilike', 'not', 'similar'))) {
            return $string;
        }

        foreach ($checks as $checkIdx => $check) {
            if ($this->stream->matchesSequence($check)) {
                $this->stream->skip(count($check));

                $escape = null;
                if ($checkIdx < 4 && $this->stream->matches(Token::TYPE_KEYWORD, self::$subType)) {
                    $pattern = $this->SubqueryExpression();

                } else {
                    $pattern = $this->OverlapsExpression();
                    if ($this->stream->matches(Token::TYPE_KEYWORD, 'escape')) {
                        $this->stream->next();
                        $escape = $this->OverlapsExpression();
                    }
                }

                return new nodes\expressions\PatternMatchingExpression(
                    $string, $pattern, implode(' ', $check), $escape
                );
            }
        }

        return $string;
    }

    protected function SubqueryExpression()
    {
        $type  = $this->stream->expect(Token::TYPE_KEYWORD, array('any', 'all', 'some'))->getValue();
        $check = $this->_checkContentsOfParentheses();
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
                $type, new nodes\lists\FunctionArgumentList(array($operand))
            );
        }
    }

    protected function OverlapsExpression()
    {
        $left = $this->BetweenExpression();

        if (!$left instanceof nodes\expressions\RowExpression
            || !$this->stream->matches(Token::TYPE_KEYWORD, 'overlaps')
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
        static $checks = array(
            array('between', 'symmetric'),
            array('between', 'asymmetric'),
            array('not', 'between', 'symmetric'),
            array('not', 'between', 'asymmetric'),
            array('between'),
            array('not', 'between')
        );

        $value = $this->InExpression();

        while ($this->stream->matches(Token::TYPE_KEYWORD, array('between', 'not'))) { // speedup
            foreach ($checks as $check) {
                if ($this->stream->matchesSequence($check)) {
                    $this->stream->skip(count($check));
                    $left  = $this->GenericOperatorExpression(true);
                    $this->stream->expect(Token::TYPE_KEYWORD, 'and');
                    // right argument of BETWEEN is defined as 'b_expr' in pre-9.5 grammar and as 'a_expr' afterwards
                    $right = $this->GenericOperatorExpression(self::OPERATOR_PRECEDENCE_PRE_9_5 === $this->precedence);
                    $value = new nodes\expressions\BetweenExpression($value, $left, $right, implode(' ', $check));
                    // perhaps non-associativity of 9.5+ BETWEEN is caused by above change to right argument?
                    if (self::OPERATOR_PRECEDENCE_PRE_9_5 === $this->precedence) {
                        continue 2;
                    } else {
                        break 2;
                    }
                }
            }
            break;
        }

        return $value;
    }

    protected function RestrictedExpression()
    {
        return self::OPERATOR_PRECEDENCE_PRE_9_5 === $this->precedence
               ? $this->ComparisonEquality(true)
               : $this->Comparison(true);
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

        while ($this->stream->matches(Token::TYPE_KEYWORD, 'in')
               || $this->stream->matches(Token::TYPE_KEYWORD, 'not')
                  && $this->stream->look(1)->matches(Token::TYPE_KEYWORD, 'in')
        ) {
            $operator = $this->stream->next()->getValue();
            if ('not' === $operator) {
                $operator .= ' ' . $this->stream->next()->getValue();
            }

            $check = $this->_checkContentsOfParentheses();
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

        while (($op = $this->_matchesOperator())
               || $this->stream->matches(Token::TYPE_SPECIAL, self::$mathOp)
                   && $this->stream->look(1)->matches(Token::TYPE_KEYWORD, self::$subType)
        ) {
            if ($op) {
                $operator = $this->Operator();
            } else {
                $operator = $this->stream->next()->getValue();
            }
            if (!$op || $this->stream->matches(Token::TYPE_KEYWORD, self::$subType)) {
                // subquery operator
                $leftOperand = new nodes\expressions\OperatorExpression(
                    $operator, $leftOperand, $this->SubqueryExpression()
                );

            } elseif (!$this->_matchesExpressionStart()) {
                // postfix operator
                return new nodes\expressions\OperatorExpression($operator, $leftOperand, null);

            } else {
                $leftOperand = new nodes\expressions\OperatorExpression(
                    $operator, $leftOperand, $this->GenericOperatorTerm($restricted)
                );
            }
        }

        return $leftOperand;
    }

    protected function GenericOperatorTerm($restricted = false)
    {
        // prefix operator(s)
        while ($this->_matchesOperator()) {
            $operators[] = $this->Operator();
        }
        $term = self::OPERATOR_PRECEDENCE_PRE_9_5 === $this->precedence
                ? $this->IsWhateverExpression($restricted)
                : $this->ArithmeticExpression($restricted);
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
        if ($this->stream->matches(Token::TYPE_OPERATOR)
            || (self::OPERATOR_PRECEDENCE_PRE_9_5 === $this->precedence
                && $this->stream->matches(Token::TYPE_INEQUALITY))
            || $all && $this->stream->matches(Token::TYPE_SPECIAL_CHAR, self::$mathOp)
        ) {
            return $this->stream->next()->getValue();
        }

        // we don't really give a fuck about qualified operator's structure, so just return it as string
        $operator = $this->stream->expect('operator')->getValue() . $this->stream->expect('(')->getValue();
        while ($this->stream->matches(Token::TYPE_IDENTIFIER)
               || $this->stream->matches(Token::TYPE_UNRESERVED_KEYWORD)
               || $this->stream->matches(Token::TYPE_COL_NAME_KEYWORD)
        ) {
            // ColId
            $operator .= new nodes\Identifier($this->stream->next());
            $operator .= $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '.')->getValue();
        }
        if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, self::$mathOp)) {
            $operator .= $this->stream->next()->getValue();
        } else {
            $operator .= $this->stream->expect(Token::TYPE_OPERATOR)->getValue();
        }
        $operator .= $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')')->getValue();

        return $operator;
    }

    protected function IsWhateverExpression($restricted = false)
    {
        $operand = self::OPERATOR_PRECEDENCE_PRE_9_5 === $this->precedence
                   ? $this->ArithmeticExpression($restricted)
                   : $this->Comparison($restricted);

        if ($restricted) {
            $checks = array();

        } else {
            $checks = array(
                array('null'),
                array('not', 'null'),
                array('true'),
                array('not', 'true'),
                array('false'),
                array('not', 'false'),
                array('unknown'),
                array('not', 'unknown')
            );
        }
        $checks = array_merge($checks, array(
            array('document'),
            array('not', 'document')
        ));

        while ($this->stream->matches(Token::TYPE_KEYWORD, 'is')
               || !$restricted && $this->stream->matches(Token::TYPE_KEYWORD, array('notnull', 'isnull'))
        ) {
            $operator = $this->stream->next()->getValue();
            if ('notnull' === $operator) {
                $operand = new nodes\expressions\OperatorExpression('is not null', $operand, null);
                continue;
            } elseif ('isnull' === $operator) {
                $operand = new nodes\expressions\OperatorExpression('is null', $operand, null);
                continue;
            }

            foreach ($checks as $check) {
                if ($this->stream->matchesSequence($check)) {
                    $this->stream->skip(count($check));
                    $operand = new nodes\expressions\OperatorExpression('is ' . implode(' ', $check), $operand, null);
                    continue 2;
                }
            }
            foreach (array(
                        array('distinct', 'from'),
                        array('not', 'distinct', 'from')
                    ) as $check
            ) {
                if ($this->stream->matchesSequence($check)) {
                    $this->stream->skip(count($check));
                    // 'is distinct from' requires parentheses
                    return new nodes\expressions\OperatorExpression(
                        'is ' . implode(' ', $check), $operand, $this->ArithmeticExpression($restricted)
                    );
                }
            }
            foreach (array(
                        array('of', '('),
                        array('not', 'of', '(')
                    ) as $check
            ) {
                if ($this->stream->matchesSequence($check)) {
                    $this->stream->skip(count($check));
                    array_pop($check); // remove '('
                    $right = $this->TypeList();
                    $this->stream->expect(')');
                    $operand = new nodes\expressions\IsOfExpression($operand, $right, 'is ' . implode(' ', $check));
                    continue 2;
                }
            }

            throw new exceptions\SyntaxException('Unexpected ' . $this->stream->getCurrent());
        }

        return $operand;
    }

    protected function TypeList()
    {
        $types = new nodes\lists\TypeList(array($this->TypeName()));

        while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
            $this->stream->next();
            $types[] = $this->TypeName();
        }

        return $types;
    }

    protected function TypeName()
    {
        $setOf  = false;
        $bounds = array();
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'setof')) {
            $this->stream->next();
            $setOf = true;
        }

        $typeName = $this->SimpleTypeName();
        $typeName->setSetOfFlag($setOf);

        if ($this->stream->matches(Token::TYPE_KEYWORD, 'array')) {
            $this->stream->next();
            if (!$this->stream->matches(Token::TYPE_SPECIAL_CHAR, '[')) {
                $bounds[] = -1;
            } else {
                $this->stream->next();
                $bounds[] = $this->stream->expect(Token::TYPE_INTEGER)->getValue();
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ']');
            }

        } else {
            while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '[')) {
                $this->stream->next();
                if ($this->stream->matches(Token::TYPE_INTEGER)) {
                    $bounds[] = $this->stream->next()->getValue();
                } else {
                    $bounds[] = -1;
                }
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ']');
            }
        }
        $typeName->setArrayBounds($bounds);

        return $typeName;
    }

    /**
     * @return nodes\TypeName
     * @throws exceptions\SyntaxException
     */
    protected function SimpleTypeName()
    {
        if (null !== ($typeName = $this->IntervalTypeName())
            || null !== ($typeName = $this->DateTimeTypeName())
            || null !== ($typeName = $this->CharacterTypeName())
            || null !== ($typeName = $this->BitTypeName())
            || null !== ($typeName = $this->NumericTypeName())
            || null !== ($typeName = $this->GenericTypeName())
        ) {
            return $typeName;
        }

        throw exceptions\SyntaxException::atPosition(
            'Expecting type name', $this->stream->getSource(), $this->stream->getCurrent()->getPosition()
        );
    }

    protected function NumericTypeName()
    {
        static $mapping = array(
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
        );

        if ($this->stream->matches(
                Token::TYPE_KEYWORD,
                array('int', 'integer', 'smallint', 'bigint', 'real', 'float', 'decimal', 'dec', 'numeric', 'boolean')
            )
            || $this->stream->matchesSequence(array('double', 'precision'))
        ) {
            $typeName  = $this->stream->next()->getValue();
            $modifiers = null;
            if ('double' === $typeName) {
                // "double precision"
                $typeName .= ' ' . $this->stream->next()->getValue();

            } else {
                if ('float' === $typeName) {
                    $floatName = 'float8';
                    if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
                        $this->stream->next();
                        $precisionToken = $this->stream->expect(Token::TYPE_INTEGER);
                        $precision      = $precisionToken->getValue();
                        if ($precision < 1) {
                            throw exceptions\SyntaxException::atPosition(
                                'Precision for type float must be at least 1 bit',
                                $this->stream->getSource(), $precisionToken->getPosition()
                            );
                        } elseif ($precision <= 24) {
                            $floatName = 'float4';
                        } elseif ($precision <= 53) {
                            $floatName = 'float8';
                        } else {
                            throw exceptions\SyntaxException::atPosition(
                                'Precision for type float must be less than 54 bits',
                                $this->stream->getSource(), $precisionToken->getPosition()
                            );
                        }
                        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
                    }
                    return new nodes\TypeName(new nodes\QualifiedName(array('pg_catalog', $floatName)));

                } elseif ('decimal' === $typeName || 'dec' === $typeName || 'numeric' === $typeName) {
                    // NB: we explicitly require constants here, per comment in gram.y:
                    // > To avoid parsing conflicts against function invocations, the modifiers
                    // > have to be shown as expr_list here, but parse analysis will only accept
                    // > constants for them.
                    if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
                        $this->stream->next();
                        $modifiers = new nodes\lists\TypeModifierList(array(
                            new nodes\Constant($this->stream->expect(Token::TYPE_INTEGER))
                        ));
                        if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
                            $this->stream->next();
                            $modifiers[] = new nodes\Constant($this->stream->expect(Token::TYPE_INTEGER));
                        }
                        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
                    }
                }
            }
            return new nodes\TypeName(
                new nodes\QualifiedName(array('pg_catalog', $mapping[$typeName])),
                $modifiers
            );
        }

        return null;
    }

    protected function BitTypeName($leading = false)
    {
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'bit')) {
            $typeName  = $this->stream->next()->getValue();
            $modifiers = null;
            if ($this->stream->matches(Token::TYPE_KEYWORD, 'varying')) {
                $this->stream->next();
                $typeName = 'varbit';
            }
            if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
                $this->stream->next();
                $modifiers = new nodes\lists\TypeModifierList(array(
                    new nodes\Constant($this->stream->expect(Token::TYPE_INTEGER))
                ));
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            }
            // BIT translates to bit(1) *unless* this is a leading typecast
            // where it translates to "any length" (with no modifiers)
            if (!$leading && $typeName === 'bit' && empty($modifiers)) {
                $modifiers = new nodes\lists\TypeModifierList(array(new nodes\Constant(1)));
            }
            return new nodes\TypeName(
                new nodes\QualifiedName(array('pg_catalog', $typeName)), $modifiers
            );
        }

        return null;
    }

    protected function CharacterTypeName($leading = false)
    {
        if ($this->stream->matches(Token::TYPE_KEYWORD, array('character', 'char', 'varchar', 'nchar'))
            || $this->stream->matches(Token::TYPE_KEYWORD, 'national')
               && $this->stream->look(1)->matches(Token::TYPE_KEYWORD, array('character', 'char'))
        ) {
            $typeName  = $this->stream->next()->getValue();
            $varying   = ('varchar' === $typeName);
            $modifiers = null;
            if ('national' === $typeName) {
                $this->stream->next();
            }
            if ('varchar' !== $typeName && $this->stream->matches(Token::TYPE_KEYWORD, 'varying')) {
                $this->stream->next();
                $varying = true;
            }
            if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
                $this->stream->next();
                $modifiers = new nodes\lists\TypeModifierList(array(
                    new nodes\Constant($this->stream->expect(Token::TYPE_INTEGER))
                ));
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            }
            // CHAR translates to char(1) *unless* this is a leading typecast
            // where it translates to "any length" (with no modifiers)
            if (!$leading && !$varying && null === $modifiers) {
                $modifiers = new nodes\lists\TypeModifierList(array(new nodes\Constant(1)));
            }
            $typeNode = new nodes\TypeName(
                new nodes\QualifiedName(array('pg_catalog', $varying ? 'varchar' : 'bpchar')),
                $modifiers
            );

            // do not allow CHARACTER SET modifier for text types, "support" for it can be traced back
            // to commit f10b63923760101f765b1d37b1fcc7adc189d778 from 1997 and behaviour is a bit strange:
            // pglibtest=# select cast('foo' as varchar character set cp1251);
            // ERROR:  type "pg_catalog.varchar_cp1251" does not exist
            if ($this->stream->matchesSequence(array('character', 'set'))) {
                throw new exceptions\NotImplementedException(
                    'Support for CHARACTER SET modifier of character types not implemented'
                );
            }

            return $typeNode;
        }

        return null;
    }

    protected function DateTimeTypeName()
    {
        if ($this->stream->matches(Token::TYPE_KEYWORD, array('time', 'timestamp'))) {
            $typeName  = $this->stream->next()->getValue();
            $modifiers = null;
            if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
                $this->stream->next();
                $modifiers = new nodes\lists\TypeModifierList(array(
                    new nodes\Constant($this->stream->expect(Token::TYPE_INTEGER))
                ));
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            }

            if ($this->stream->matchesSequence(array(array('with', 'without'), 'time', 'zone'))) {
                if ('with' === $this->stream->next()->getValue()) {
                    $typeName .= 'tz';
                }
                $this->stream->skip(2);
            }
            return new nodes\TypeName(new nodes\QualifiedName(array('pg_catalog', $typeName)), $modifiers);
        }

        return null;
    }


    protected function IntervalTypeName($leading = false)
    {
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'interval')) {
            $token     = $this->stream->next();
            $modifiers = null;
            $operand   = null;
            if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
                $this->stream->next();
                $modifiers = new nodes\lists\TypeModifierList(array(
                    new nodes\Constant($this->stream->expect(Token::TYPE_INTEGER))
                ));
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            }
            if ($leading) {
                $operand = new nodes\Constant($this->stream->expect(Token::TYPE_STRING));
            }

            if ($this->stream->matches(Token::TYPE_KEYWORD, array('year', 'month', 'day', 'hour', 'minute', 'second'))) {
                $trailing  = array($this->stream->next()->getValue());
                $second    = 'second' === $trailing[0];
                if ($this->stream->matches(Token::TYPE_KEYWORD, 'to')) {
                    $toToken    = $this->stream->next();
                    $trailing[] = 'to';
                    if ('year' === $trailing[0]) {
                        $end = $this->stream->expect(Token::TYPE_KEYWORD, 'month');
                    } elseif ('day' === $trailing[0]) {
                        $end = $this->stream->expect(Token::TYPE_KEYWORD, array('hour', 'minute', 'second'));
                    } elseif ('hour' === $trailing[0]) {
                        $end = $this->stream->expect(Token::TYPE_KEYWORD, array('minute', 'second'));
                    } elseif ('minute' === $trailing[0]) {
                        $end = $this->stream->expect(Token::TYPE_KEYWORD, 'second');
                    } else {
                        throw new exceptions\SyntaxException('Unexpected ' . $toToken);
                    }
                    $second     = 'second' === $end->getValue();
                    $trailing[] = $end->getValue();
                }

                if ($second && $this->stream->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
                    if (null !== $modifiers) {
                        throw new exceptions\SyntaxException('Interval precision specified twice for ' . $token);
                    }
                    $this->stream->next();
                    $modifiers = new nodes\lists\TypeModifierList(array(
                        new nodes\Constant($this->stream->expect(Token::TYPE_INTEGER))
                    ));
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

    /**
     *
     * @return string|bool
     */
    protected function GenericTypeName()
    {
        if ($this->stream->matches(Token::TYPE_IDENTIFIER)
            || $this->stream->matches(Token::TYPE_UNRESERVED_KEYWORD)
            || $this->stream->matches(Token::TYPE_TYPE_FUNC_NAME_KEYWORD)
        ) {
            $typeName  = array(new nodes\Identifier($this->stream->next()));
            $modifiers = null;
            while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '.')) {
                $this->stream->next();
                if ($this->stream->matches(Token::TYPE_IDENTIFIER)) {
                    $typeName[] = new nodes\Identifier($this->stream->next());
                } else {
                    // any keyword goes, see ColLabel
                    $typeName[] = new nodes\Identifier($this->stream->expect(Token::TYPE_KEYWORD));
                }
            }

            if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
                $this->stream->next();
                $modifiers = new nodes\lists\TypeModifierList(array($this->GenericTypeModifier()));
                while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
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
        if ($this->stream->matches(Token::TYPE_INTEGER)
            || $this->stream->matches(Token::TYPE_FLOAT)
            || $this->stream->matches(Token::TYPE_STRING)
        ) {
            return new nodes\Constant($this->stream->next());

        } elseif ($this->stream->matches(Token::TYPE_IDENTIFIER)
            || $this->stream->matches(Token::TYPE_UNRESERVED_KEYWORD)
            || $this->stream->matches(Token::TYPE_TYPE_FUNC_NAME_KEYWORD)
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

        while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, array('+', '-'))) {
            $operator = $this->stream->next()->getValue();
            $leftOperand = new nodes\expressions\OperatorExpression(
                $operator, $leftOperand, $this->ArithmeticTerm($restricted)
            );
        }

        return $leftOperand;
    }

    protected function ArithmeticTerm($restricted = false)
    {
        $leftOperand = $this->ArithmeticMultiplier($restricted);

        while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, array('*', '/', '%'))) {
            $operator = $this->stream->next()->getValue();
            $leftOperand = new nodes\expressions\OperatorExpression(
                $operator, $leftOperand, $this->ArithmeticMultiplier($restricted)
            );
        }

        return $leftOperand;
    }

    protected function ArithmeticMultiplier($restricted = false)
    {
        $leftOperand = $restricted
                       ? $this->UnaryPlusMinusExpression()
                       : $this->AtTimeZoneExpression();

        while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '^')) {
            $operator    = $this->stream->next()->getValue();
            $leftOperand = new nodes\expressions\OperatorExpression(
                $operator, $leftOperand,
                $restricted ? $this->UnaryPlusMinusExpression() : $this->AtTimeZoneExpression()
            );
        }

        return $leftOperand;
    }

    protected function AtTimeZoneExpression()
    {
        $left = $this->CollateExpression();
        if ($this->stream->matchesSequence(array('at', 'time', 'zone'))) {
            $this->stream->skip(3);
            return new nodes\expressions\OperatorExpression('at time zone', $left, $this->CollateExpression());
        }
        return $left;
    }

    protected function CollateExpression()
    {
        $left = $this->UnaryPlusMinusExpression();
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'collate')) {
            $this->stream->next();
            return new nodes\expressions\CollateExpression($left, $this->QualifiedName());
        }
        return $left;
    }

    /**
     * @return nodes\expressions\OperatorExpression|string
     */
    protected function UnaryPlusMinusExpression()
    {
        if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, array('+', '-'))) {
            $operator = $this->stream->next()->getValue();
            $operand  = $this->UnaryPlusMinusExpression();
            if (!$operand instanceof nodes\Constant
                || !in_array($operand->type, array(Token::TYPE_INTEGER, Token::TYPE_FLOAT))
                || '-' !== $operator
            ) {
                return new nodes\expressions\OperatorExpression($operator, null, $operand);

            } else {
                if ('-' === $operand->value[0]) {
                    return new nodes\Constant(new Token($operand->type, substr($operand->value, 1), null));
                } else {
                    return new nodes\Constant(new Token($operand->type, '-' . $operand->value, null));
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
        // this will return null if stream is not at opening parenthesis, so we don't check for that
        $check = $this->_checkContentsOfParentheses();

        if ('row' === $check
            || $this->stream->matchesSequence(array('row', '('))
        ) {
            return $this->RowConstructor();

        } elseif ($this->stream->matches(Token::TYPE_KEYWORD, 'array')) {
            return $this->ArrayConstructor();

        } elseif ($this->stream->matches(Token::TYPE_KEYWORD, 'exists')) {
            $this->stream->next();
            return new nodes\expressions\SubselectExpression($this->SelectWithParentheses(), 'exists');

        } elseif ($this->stream->matches(Token::TYPE_KEYWORD, 'case')) {
            return $this->CaseExpression();

        } elseif ('select' === $check) {
            $atom = new nodes\expressions\SubselectExpression($this->SelectWithParentheses());

        } elseif ('expression' === $check) {
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
            $atom = $this->Expression();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        } elseif ($this->stream->matches(Token::TYPE_PARAMETER)) {
            $atom = new nodes\Parameter($this->stream->next());

        } elseif ($this->stream->matches(Token::TYPE_LITERAL)
                  || $this->stream->matches(Token::TYPE_KEYWORD, array('true', 'false', 'null'))
        ) {
            return new nodes\Constant($this->stream->next());

        } elseif ($this->_matchesTypecast()) {
            return $this->LeadingTypecast();

        } elseif ($this->_matchesFunctionCall()) {
            return $this->FunctionExpression();

        } else {
            return $this->ColumnReference();
        }

        if ($indirection = $this->Indirection()) {
            return new nodes\Indirection($indirection, $atom);
        }

        return $atom;
    }

    protected function RowConstructor()
    {
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'row')) {
            $this->stream->next();
        }

        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
        if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ')')) {
            $fields = array();
        } else {
            $fields = $this->ExpressionList();
        }
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        return new nodes\expressions\RowExpression($fields);
    }

    protected function ArrayConstructor()
    {
        $this->stream->expect(Token::TYPE_KEYWORD, 'array');
        if (!$this->stream->matches(Token::TYPE_SPECIAL_CHAR, array('[', '('))) {
            throw exceptions\SyntaxException::expectationFailed(
                Token::TYPE_SPECIAL_CHAR, array('[', '('), $this->stream->getCurrent(), $this->stream->getSource()
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
        if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ']')) {
            $expression = array();

        } elseif (!$this->stream->matches(Token::TYPE_SPECIAL_CHAR, '[')) {
            $expression = $this->ExpressionList();

        } else {
            $expression = array($this->ArrayExpression());
            while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
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
        $whenClauses = array();
        $elseClause  = null;

        $this->stream->expect(Token::TYPE_KEYWORD, 'case');
        // "simple" variant?
        if (!$this->stream->matches(Token::TYPE_KEYWORD, 'when')) {
            $argument = $this->Expression();
        }

        // requires at least one WHEN clause
        do {
            $this->stream->expect(Token::TYPE_KEYWORD, 'when');
            $when = $this->Expression();
            $this->stream->expect(Token::TYPE_KEYWORD, 'then');
            $then = $this->Expression();
            $whenClauses[] = new nodes\expressions\WhenExpression($when, $then);
        } while ($this->stream->matches(Token::TYPE_KEYWORD, 'when'));

        // may have an ELSE clause
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'else')) {
            $this->stream->next();
            $elseClause = $this->Expression();
        }
        $this->stream->expect(Token::TYPE_KEYWORD, 'end');

        return new nodes\expressions\CaseExpression($whenClauses, $elseClause, $argument);
    }

    protected function LeadingTypecast()
    {
        if (null !== ($typeCast = $this->IntervalTypeName(true))) {
            // interval is a special case since its options may come *after* string constant
            return $typeCast;
        }

        if (null !== ($typeName = $this->DateTimeTypeName())
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
        static $mapFunctions = array(
            'current_role'    => 'current_user',
            'user'            => 'current_user',
            'current_catalog' => 'current_database'
        );

        if (!$this->stream->matches(Token::TYPE_KEYWORD, array(
                'current_date', 'current_role', 'current_user', 'session_user',
                'user', 'current_catalog', 'current_schema'
            ))
        ) {
            return null;
        }

        $funcName = $this->stream->next()->getValue();
        if ('current_date' === $funcName) {
            // we convert to 'now'::date instead of 'now'::text::date, since the latter is only
            // needed for rules, default values and such. we don't do these
            return new nodes\expressions\TypecastExpression(
                new nodes\Constant('now'),
                new nodes\TypeName(new nodes\QualifiedName(array('pg_catalog', 'date')))
            );

        } elseif (isset($mapFunctions[$funcName])) {
            return new nodes\FunctionCall(new nodes\QualifiedName(array('pg_catalog', $mapFunctions[$funcName])));

        } else {
            return new nodes\FunctionCall(new nodes\QualifiedName(array('pg_catalog', $funcName)));
        }
    }

    protected function SystemFunctionCallOptionalParens()
    {
        static $mapTypes = array(
            'current_time'      => 'timetz',
            'current_timestamp' => 'timestamptz',
            'localtime'         => 'time',
            'localtimestamp'    => 'timestamp'
        );

        if (!$this->stream->matches(Token::TYPE_KEYWORD, array(
                'current_time', 'current_timestamp', 'localtime', 'localtimestamp'
            ))
        ) {
            return null;
        }

        $funcName = $this->stream->next()->getValue();
        if (!$this->stream->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
            $modifiers = null;
        } else {
            $this->stream->next();
            $modifiers = new nodes\lists\TypeModifierList(array(
                new nodes\Constant($this->stream->expect(Token::TYPE_INTEGER))
            ));
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
        }
        $typeName = new nodes\TypeName(
            new nodes\QualifiedName(array('pg_catalog', $mapTypes[$funcName])), $modifiers
        );
        return new nodes\expressions\TypecastExpression(new nodes\Constant('now'), $typeName);
    }

    protected function SystemFunctionCallRequiredParens()
    {
        if (!$this->stream->matches(Token::TYPE_KEYWORD, array(
                'cast', 'extract', 'overlay', 'position', 'substring', 'treat', 'trim',
                'nullif', 'coalesce', 'greatest', 'least', 'xmlconcat', 'xmlelement',
                'xmlexists', 'xmlforest', 'xmlparse', 'xmlpi', 'xmlroot', 'xmlserialize'
            ))
        ) {
            return null;
        }
        $funcName  = $this->stream->next()->getValue();
        $arguments = array();
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
            if ($this->stream->matches(Token::TYPE_KEYWORD, array('year', 'month', 'day', 'hour', 'minute', 'second'))
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
            if ($this->stream->matches(Token::TYPE_KEYWORD, 'for')) {
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
            $arguments = new nodes\lists\FunctionArgumentList(array($this->Expression()));
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ',');
            $arguments[] = $this->Expression();
            $funcNode    = new nodes\FunctionCall('nullif', $arguments);
            break;

        case 'xmlelement':
            $funcNode = $this->XmlElementFunction();
            break;

        case 'xmlexists':
            $arguments[] = $this->ExpressionAtom();
            $this->stream->expect(Token::TYPE_KEYWORD, 'passing');
            if ($this->stream->matchesSequence(array('by', 'ref'))) {
                $this->stream->next();
                $this->stream->next();
            }
            $arguments[] = $this->ExpressionAtom();
            if ($this->stream->matchesSequence(array('by', 'ref'))) {
                $this->stream->next();
                $this->stream->next();
            }
            break;

        case 'xmlforest':
            $funcNode = new nodes\xml\XmlForest($this->XmlAttributeList());
            break;

        case 'xmlparse':
            $docOrContent = $this->stream->expect(Token::TYPE_KEYWORD, array('document', 'content'))->getValue();
            $value        = $this->Expression();
            $preserve     = false;
            if ($this->stream->matchesSequence(array('preserve', 'whitespace'))) {
                $preserve = true;
                $this->stream->next();
                $this->stream->next();
            } elseif ($this->stream->matchesSequence(array('strip', 'whitespace'))) {
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
            if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
                $this->stream->next();
                $content = $this->Expression();
            }

            $funcNode = new nodes\xml\XmlPi($name, $content);
            break;

        case 'xmlroot':
            $funcNode = $this->XmlRoot();
            break;

        case 'xmlserialize':
            $docOrContent = $this->stream->expect(Token::TYPE_KEYWORD, array('document', 'content'))->getValue();
            $value        = $this->Expression();
            $this->stream->expect(Token::TYPE_KEYWORD, 'as');
            $typeName     = $this->SimpleTypeName();
            $funcNode     = new nodes\xml\XmlSerialize($docOrContent, $value, $typeName);
            break;

        default: // 'coalesce', 'greatest', 'least', 'xmlconcat'
            $funcNode = new nodes\FunctionCall(
                $funcName, new nodes\lists\FunctionArgumentList($this->ExpressionList())
            );
        }

        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
        if (empty($funcNode)) {
            $funcNode = new nodes\FunctionCall(
                new nodes\QualifiedName(array('pg_catalog', $funcName)),
                new nodes\lists\FunctionArgumentList($arguments)
            );
        }
        return $funcNode;
    }

    protected function TrimFunctionArguments()
    {
        if (!$this->stream->matches(Token::TYPE_KEYWORD, array('both', 'leading', 'trailing'))) {
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

        if ($this->stream->matches(Token::TYPE_KEYWORD, 'from')) {
            $this->stream->next();
            $arguments = $this->ExpressionList();
        } else {
            $first = $this->Expression();
            if ($this->stream->matches(Token::TYPE_KEYWORD, 'from')) {
                $this->stream->next();
                $arguments   = $this->ExpressionList();
                $arguments[] = $first;
            } elseif ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
                $this->stream->next();
                $arguments = new nodes\lists\ExpressionList(array($first));
                $arguments->merge($this->ExpressionList());
            } else {
                $arguments = array($first);
            }
        }

        return array($funcName, $arguments);
    }

    protected function SubstringFunctionArguments()
    {
        $arguments = new nodes\lists\FunctionArgumentList(array($this->Expression()));
        $from  = $for = null;
        if (!$this->stream->matches(Token::TYPE_KEYWORD, array('from', 'for'))) {
            // generic expr_list, 'from' and 'for' are intended for exception message
            $this->stream->expect(array(',', 'from', 'for'));
            $arguments->merge($this->ExpressionList());

        } else {
            if ('from' === $this->stream->next()->getValue()) {
                $from = $this->Expression();
            } else {
                $for  = $this->Expression();
            }
            if ($this->stream->matches(Token::TYPE_KEYWORD, array('from', 'for'))) {
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
            $arguments->merge(array($from, $for));
        } elseif ($from) {
            $arguments[] = $from;
        } elseif ($for) {
            $arguments->merge(array(new nodes\Constant(1), $for));
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
        if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
            $this->stream->next();
            if (!$this->stream->matches(Token::TYPE_KEYWORD, 'xmlattributes')) {
                $content = $this->ExpressionList();
            } else {
                $this->stream->next();
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
                $attributes = new nodes\lists\TargetList($this->XmlAttributeList());
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
                if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
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
        if ($this->stream->matchesSequence(array('no', 'value'))) {
            $version = null;
        } else {
            $version = $this->Expression();
        }
        if (!$this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
            $standalone = null;
        } else {
            $this->stream->next();
            $this->stream->expect(Token::TYPE_KEYWORD, 'standalone');
            if ($this->stream->matchesSequence(array('no', 'value'))) {
                $this->stream->next();
                $this->stream->next();
                $standalone = 'no value';
            } else {
                $standalone = $this->stream->expect(Token::TYPE_KEYWORD, array('yes', 'no'))->getValue();
            }
        }
        return new nodes\xml\XmlRoot($xml, $version, $standalone);
    }

    protected function XmlAttributeList()
    {
        $attributes = array($this->XmlAttribute());

        while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
            $this->stream->next();
            $attributes[] = $this->XmlAttribute();
        }

        return $attributes;
    }

    protected function XmlAttribute()
    {
        $value   = $this->Expression();
        $attname = null;
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'as')) {
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
                        clone $function->arguments, $function->distinct, $function->variadic,
                        clone $function->order
                     )
                   : $function;
        }

        $function    = $this->GenericFunctionCall();
        $withinGroup = false;
        $order       = $filter = $over = null;

        if ($this->stream->matchesSequence(array('within', 'group'))) {
            if (count($function->order) > 0) {
                throw exceptions\SyntaxException::atPosition(
                    'Cannot use multiple ORDER BY clauses with WITHIN GROUP',
                    $this->stream->getSource(),  $this->stream->getCurrent()->getPosition()
                );
            }
            if ($function->distinct) {
                throw exceptions\SyntaxException::atPosition(
                    'Cannot use DISTINCT with WITHIN GROUP',
                    $this->stream->getSource(),  $this->stream->getCurrent()->getPosition()
                );
            }
            if ($function->variadic) {
                throw exceptions\SyntaxException::atPosition(
                    'Cannot use VARIADIC with WITHIN GROUP',
                    $this->stream->getSource(),  $this->stream->getCurrent()->getPosition()
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
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'filter')) {
            $this->stream->next();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
            $this->stream->expect(Token::TYPE_KEYWORD, 'where');
            $filter = $this->Expression();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
        }
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'over')) {
            $this->stream->next();
            $over = $this->WindowSpecification();
        }
        return new nodes\expressions\FunctionExpression(
            is_object($function->name) ? clone $function->name : $function->name,
            clone $function->arguments, $function->distinct, $function->variadic,
            $order ?: clone $function->order, $withinGroup, $filter, $over
        );
    }

    protected function SpecialFunctionCall()
    {
        if (null !== ($funcNode = $this->SystemFunctionCallNoParens())
            || null !== ($funcNode = $this->SystemFunctionCallOptionalParens())
            || null !== ($funcNode = $this->SystemFunctionCallRequiredParens())
        ) {
            return $funcNode;

        } elseif ($this->stream->matchesSequence(array('collation', 'for'))) {
            $this->stream->skip(2);
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
            $argument = $this->Expression();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            return new nodes\FunctionCall(
                new nodes\QualifiedName(array('pg_catalog', 'pg_collation_for')),
                new nodes\lists\FunctionArgumentList(array($argument))
            );
        }

        return null;
    }

    protected function GenericFunctionCall()
    {
        if ($this->stream->matches(Token::TYPE_KEYWORD)
            && !$this->stream->matches(Token::TYPE_RESERVED_KEYWORD)
        ) {
            $firstToken = $this->stream->next();
        } else {
            $firstToken = $this->stream->expect(Token::TYPE_IDENTIFIER);
        }
        $funcName = array(new nodes\Identifier($firstToken));

        while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '.')) {
            $this->stream->next();
            if ($this->stream->matches(Token::TYPE_KEYWORD)) {
                $funcName[] = new nodes\Identifier($this->stream->next());
            } else {
                $funcName[] = new nodes\Identifier($this->stream->expect(Token::TYPE_IDENTIFIER));
            }
        }

        if (Token::TYPE_TYPE_FUNC_NAME_KEYWORD === $firstToken->getType() && 1 < count($funcName)
            || Token::TYPE_COL_NAME_KEYWORD === $firstToken->getType() && 1 === count($funcName)
        ) {
            throw exceptions\SyntaxException::atPosition(
                implode('.', $funcName) . ' is not a valid function name',
                $this->stream->getSource(),  $firstToken->getPosition()
            );
        }

        $positionalArguments = $namedArguments = array();
        $variadic = $distinct = false;
        $orderBy = null;

        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
        if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '*')) {
            $this->stream->next();
            $positionalArguments = new nodes\Star();

        } elseif (!$this->stream->matches(Token::TYPE_SPECIAL_CHAR, ')')) {
            if ($this->stream->matches(Token::TYPE_KEYWORD, array('distinct', 'all'))) {
                $distinct = 'distinct' === $this->stream->next()->getValue();
            }
            list($value, $name, $variadic) = $this->GenericFunctionArgument();
            if (!$name) {
                $positionalArguments[] = $value;
            } else {
                $namedArguments[(string)$name] = $value;
            }

            while (!$variadic && $this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
                $this->stream->next();

                $argToken = $this->stream->getCurrent();
                list($value, $name, $variadic) = $this->GenericFunctionArgument();
                if (!$name) {
                    if (empty($namedArguments)) {
                        $positionalArguments[] = $value;
                    } else {
                        throw exceptions\SyntaxException::atPosition(
                            'Positional argument cannot follow named argument',
                            $this->stream->getSource(), $argToken->getPosition()
                        );
                    }
                } elseif (!isset($namedArguments[(string)$name])) {
                    $namedArguments[(string)$name] = $value;
                } else {
                    throw exceptions\SyntaxException::atPosition(
                        "Argument name {$name} used more than once",
                        $this->stream->getSource(), $argToken->getPosition()
                    );
                }
            }
            if ($this->stream->matchesSequence(array('order', 'by'))) {
                $this->stream->skip(2);
                $orderBy = $this->OrderByList();
            }
        }
        $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        return new nodes\FunctionCall(
            new nodes\QualifiedName($funcName),
            $positionalArguments instanceof nodes\Star
            ? $positionalArguments : new nodes\lists\FunctionArgumentList($positionalArguments + $namedArguments),
            $distinct, $variadic, $orderBy
        );
    }

    protected function GenericFunctionArgument()
    {
        if ($variadic = $this->stream->matches(Token::TYPE_KEYWORD, 'variadic')) {
            $this->stream->next();
        }

        $name = null;
        // it's the only place this shit can appear in
        if ($this->stream->look(1)->matches(Token::TYPE_COLON_EQUALS)) {
            if ($this->stream->matches(Token::TYPE_UNRESERVED_KEYWORD)
                || $this->stream->matches(Token::TYPE_TYPE_FUNC_NAME_KEYWORD)
            ) {
                $name = new nodes\Identifier($this->stream->next());
            } else {
                $name = new nodes\Identifier($this->stream->expect(Token::TYPE_IDENTIFIER));
            }
            $this->stream->next();
        }

        return array($this->Expression(), $name, $variadic);
    }

    protected function ColumnReference()
    {
        $parts = array($this->ColId());

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
        $indirection = array();
        while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, array('[', '.'))) {
            if ('.' === $this->stream->next()->getValue()) {
                if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '*')) {
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
                $lower = $this->Expression();
                $upper = null;
                if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ':')) {
                    $this->stream->next();
                    $upper = $this->Expression();
                }
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ']');

                $indirection[] = new nodes\ArrayIndexes($lower, $upper);
            }
        }
        return $indirection;
    }

    protected function TargetList()
    {
        $elements = new nodes\lists\TargetList(array($this->TargetElement()));

        while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
            $this->stream->next();
            $elements[] = $this->TargetElement();
        }

        return $elements;
    }

    protected function TargetElement()
    {
        $alias = null;

        if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '*')) {
            $this->stream->next();
            return new nodes\Star();
        }
        $element = $this->Expression();
        if ($this->stream->matches(Token::TYPE_IDENTIFIER)) {
            $alias = new nodes\Identifier($this->stream->next());

        } elseif ($this->stream->matches(Token::TYPE_KEYWORD, 'as')) {
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
        $relations = new nodes\lists\FromList(array($this->FromElement()));

        while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
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

        while ($this->stream->matches(
                Token::TYPE_KEYWORD,
                array('cross', 'natural', 'left', 'right', 'full', 'inner', 'join')
            )
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

            if ($this->stream->matches(Token::TYPE_KEYWORD, 'join')) {
                $this->stream->next();
                $joinType = 'inner';
            } else {
                $joinType = $this->stream->expect(Token::TYPE_KEYWORD, array('left', 'right', 'full', 'inner'))
                                ->getValue();
                // noise word
                if ($this->stream->matches(Token::TYPE_KEYWORD, 'outer')) {
                    $this->stream->next();
                }
                $this->stream->expect(Token::TYPE_KEYWORD, 'join');
            }
            $left = new nodes\range\JoinExpression($left, $this->TableReference(), $joinType);

            if ($natural) {
                $left->setNatural(true);

            } else {
                $token = $this->stream->expect(Token::TYPE_KEYWORD, array('on', 'using'));
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
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'lateral')) {
            $this->stream->next();
            // lateral can only apply to subselects or function invocations
            if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
                $reference = $this->RangeSubselect();
            } else {
                $reference = $this->RangeFunctionCall();
            }
            $reference->setLateral(true);

        } elseif ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
            // parentheses may contain either a subselect or JOIN expression
            if ('select' === $this->_checkContentsOfParentheses()) {
                $reference = $this->RangeSubselect();
            } else {
                $this->stream->next();
                $reference = $this->FromElement();
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
                if ($alias = $this->OptionalAliasClause()) {
                    $reference->setAlias($alias[0], $alias[1]);
                }
            }

        } elseif ($this->stream->matchesSequence(array('rows', 'from'))
                  || $this->_matchesFunctionCall()
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
                $this->stream->getSource(), $token->getPosition()
            );
        }
        $reference->setAlias($alias[0], $alias[1]);

        return $reference;
    }

    protected function RangeFunctionCall()
    {
        if ($this->stream->matchesSequence(array('rows', 'from'))) {
            $this->stream->skip(2);
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
            $list = new nodes\lists\RowsFromList(array($this->RowsFromElement()));
            while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
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

        if ($this->stream->matchesSequence(array('with', 'ordinality'))) {
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

        if (!$this->stream->matches(Token::TYPE_KEYWORD, 'as')) {
            $aliases = null;
        } else {
            $this->stream->next();
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');
            $aliases = new nodes\lists\ColumnDefinitionList(array($this->TableFuncElement()));
            while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
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

    protected function RelationExpression($statementType = 'select')
    {
        $inherit           = null;
        $expectParenthesis = false;
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'only')) {
            $this->stream->next();
            $inherit = false;
            if ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
                $expectParenthesis = true;
                $this->stream->next();
            }
        }

        $name = $this->QualifiedName();

        if (false === $inherit && $expectParenthesis) {
            $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');

        } elseif (null === $inherit && $this->stream->matches(Token::TYPE_SPECIAL_CHAR, '*')) {
            $this->stream->next();
            $inherit = true;
        }

        $expression = new nodes\range\RelationReference($name, $inherit);
        if ('select' === $statementType && ($alias = $this->OptionalAliasClause())) {
            $expression->setAlias($alias[0], $alias[1]);
        } elseif ('select' !== $statementType && ($alias = $this->DMLAliasClause($statementType))) {
            $expression->setAlias($alias);
        }

        return $expression;
    }

    /**
     *
     * Corresponds to relation_expr_opt_alias production from grammar, see the
     * comment there.
     *
     * @param $statementType
     * @return nodes\Identifier|null
     */
    protected function DMLAliasClause($statementType)
    {
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'as')
            || $this->stream->matches(Token::TYPE_IDENTIFIER)
            || $this->stream->matches(Token::TYPE_COL_NAME_KEYWORD)
            || ($this->stream->matches(Token::TYPE_UNRESERVED_KEYWORD)
                && ('update' !== $statementType || 'set' !== $this->stream->getCurrent()->getValue()))
        ) {
            if ($this->stream->matches(Token::TYPE_KEYWORD, 'as')) {
                $this->stream->next();
            }
            return $this->ColId();
        }
        return null;
    }

    protected function OptionalAliasClause($functionAlias = false)
    {
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'as')
            || $this->stream->matches(Token::TYPE_IDENTIFIER)
            || $this->stream->matches(Token::TYPE_UNRESERVED_KEYWORD)
            || $this->stream->matches(Token::TYPE_COL_NAME_KEYWORD)
        ) {
            $tableAlias    = null;
            $columnAliases = null;

            // AS is complete noise here, unlike in TargetList
            if ($this->stream->matches(Token::TYPE_KEYWORD, 'as')) {
                $this->stream->next();
            }
            if (!$functionAlias || !$this->stream->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
                $tableAlias = $this->ColId();
            }
            if (!$tableAlias || $this->stream->matches(Token::TYPE_SPECIAL_CHAR, '(')) {
                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, '(');

                $tableFuncElement = $functionAlias
                                    // for TableFuncElement this position will contain typename
                                    && (!$this->stream->look(1)->matches(Token::TYPE_SPECIAL_CHAR, array(')', ','))
                                        || !$tableAlias);

                $columnAliases = $tableFuncElement
                                 ? new nodes\lists\ColumnDefinitionList(array($this->TableFuncElement()))
                                 : new nodes\lists\IdentifierList(array($this->ColId()));
                while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
                    $this->stream->next();
                    $columnAliases[] = $tableFuncElement ? $this->TableFuncElement() : $this->ColId();
                }

                $this->stream->expect(Token::TYPE_SPECIAL_CHAR, ')');
            }

            return array($tableAlias, $columnAliases);
        }
        return null;
    }

    protected function TableFuncElement()
    {
        $alias     = $this->ColId();
        $type      = $this->TypeName();
        $collation = null;
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'collate')) {
            $this->stream->next();
            $collation = $this->QualifiedName();
        }

        return new nodes\range\ColumnDefinition($alias, $type, $collation);
    }

    protected function ColIdList()
    {
        $list = new nodes\lists\IdentifierList(array($this->ColId()));
        while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
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
        if ($this->stream->matches(Token::TYPE_UNRESERVED_KEYWORD)
            || $this->stream->matches(Token::TYPE_COL_NAME_KEYWORD)
        ) {
            return new nodes\Identifier($this->stream->next());
        } else {
            return new nodes\Identifier($this->stream->expect(Token::TYPE_IDENTIFIER));
        }
    }

    protected function QualifiedName()
    {
        $parts = array($this->ColId());

        while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, '.')) {
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
        $items = new nodes\lists\OrderByList(array($this->OrderByElement()));

        while ($this->stream->matches(Token::TYPE_SPECIAL_CHAR, ',')) {
            $this->stream->next();
            $items[] = $this->OrderByElement();
        }

        return $items;
    }

    protected function OrderByElement()
    {
        $expression = $this->Expression();
        $operator   = $direction = $nullsOrder = null;
        if ($this->stream->matches(Token::TYPE_KEYWORD, array('asc', 'desc', 'using'))) {
            if ('using' === ($direction = $this->stream->next()->getValue())) {
                $operator = $this->Operator(true);
            }
        }
        if ($this->stream->matches(Token::TYPE_KEYWORD, 'nulls')) {
            $this->stream->next();
            $nullsOrder = $this->stream->expect(Token::TYPE_KEYWORD, array('first', 'last'))->getValue();
        }

        return new nodes\OrderByElement($expression, $direction, $nullsOrder, $operator);
    }
}