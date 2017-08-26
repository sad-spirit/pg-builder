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

/**
 * Scans an SQL string for tokens
 *
 * @todo Support for Unicode escapes in strings U&'...' and identifiers U&"..."
 */
class Lexer
{
    /**
     * Characters that may appear in operators
     */
    const CHARS_OPERATOR = '~!@#^&|`?+-*/%<>=';

    /**
     * Characters that should be returned as Token::TYPE_SPECIAL_CHAR
     */
    const CHARS_SPECIAL  = ',()[].;:+-*/%^<>=';

    /**
     * List of all keywords recognized by PostgreSQL
     * Source: src/include/parser/kwlist.h
     * @var array
     */
    protected static $keywords = array(
        'abort'              => Token::TYPE_UNRESERVED_KEYWORD,
        'absolute'           => Token::TYPE_UNRESERVED_KEYWORD,
        'access'             => Token::TYPE_UNRESERVED_KEYWORD,
        'action'             => Token::TYPE_UNRESERVED_KEYWORD,
        'add'                => Token::TYPE_UNRESERVED_KEYWORD,
        'admin'              => Token::TYPE_UNRESERVED_KEYWORD,
        'after'              => Token::TYPE_UNRESERVED_KEYWORD,
        'aggregate'          => Token::TYPE_UNRESERVED_KEYWORD,
        'all'                => Token::TYPE_RESERVED_KEYWORD,
        'also'               => Token::TYPE_UNRESERVED_KEYWORD,
        'alter'              => Token::TYPE_UNRESERVED_KEYWORD,
        'always'             => Token::TYPE_UNRESERVED_KEYWORD,
        'analyse'            => Token::TYPE_RESERVED_KEYWORD,
        'analyze'            => Token::TYPE_RESERVED_KEYWORD,
        'and'                => Token::TYPE_RESERVED_KEYWORD,
        'any'                => Token::TYPE_RESERVED_KEYWORD,
        'array'              => Token::TYPE_RESERVED_KEYWORD,
        'as'                 => Token::TYPE_RESERVED_KEYWORD,
        'asc'                => Token::TYPE_RESERVED_KEYWORD,
        'assertion'          => Token::TYPE_UNRESERVED_KEYWORD,
        'assignment'         => Token::TYPE_UNRESERVED_KEYWORD,
        'asymmetric'         => Token::TYPE_RESERVED_KEYWORD,
        'at'                 => Token::TYPE_UNRESERVED_KEYWORD,
        'attribute'          => Token::TYPE_UNRESERVED_KEYWORD,
        'authorization'      => Token::TYPE_TYPE_FUNC_NAME_KEYWORD,
        'backward'           => Token::TYPE_UNRESERVED_KEYWORD,
        'before'             => Token::TYPE_UNRESERVED_KEYWORD,
        'begin'              => Token::TYPE_UNRESERVED_KEYWORD,
        'between'            => Token::TYPE_COL_NAME_KEYWORD,
        'bigint'             => Token::TYPE_COL_NAME_KEYWORD,
        'binary'             => Token::TYPE_TYPE_FUNC_NAME_KEYWORD,
        'bit'                => Token::TYPE_COL_NAME_KEYWORD,
        'boolean'            => Token::TYPE_COL_NAME_KEYWORD,
        'both'               => Token::TYPE_RESERVED_KEYWORD,
        'by'                 => Token::TYPE_UNRESERVED_KEYWORD,
        'cache'              => Token::TYPE_UNRESERVED_KEYWORD,
        'called'             => Token::TYPE_UNRESERVED_KEYWORD,
        'cascade'            => Token::TYPE_UNRESERVED_KEYWORD,
        'cascaded'           => Token::TYPE_UNRESERVED_KEYWORD,
        'case'               => Token::TYPE_RESERVED_KEYWORD,
        'cast'               => Token::TYPE_RESERVED_KEYWORD,
        'catalog'            => Token::TYPE_UNRESERVED_KEYWORD,
        'chain'              => Token::TYPE_UNRESERVED_KEYWORD,
        'char'               => Token::TYPE_COL_NAME_KEYWORD,
        'character'          => Token::TYPE_COL_NAME_KEYWORD,
        'characteristics'    => Token::TYPE_UNRESERVED_KEYWORD,
        'check'              => Token::TYPE_RESERVED_KEYWORD,
        'checkpoint'         => Token::TYPE_UNRESERVED_KEYWORD,
        'class'              => Token::TYPE_UNRESERVED_KEYWORD,
        'close'              => Token::TYPE_UNRESERVED_KEYWORD,
        'cluster'            => Token::TYPE_UNRESERVED_KEYWORD,
        'coalesce'           => Token::TYPE_COL_NAME_KEYWORD,
        'collate'            => Token::TYPE_RESERVED_KEYWORD,
        'collation'          => Token::TYPE_TYPE_FUNC_NAME_KEYWORD,
        'column'             => Token::TYPE_RESERVED_KEYWORD,
        'comment'            => Token::TYPE_UNRESERVED_KEYWORD,
        'comments'           => Token::TYPE_UNRESERVED_KEYWORD,
        'commit'             => Token::TYPE_UNRESERVED_KEYWORD,
        'committed'          => Token::TYPE_UNRESERVED_KEYWORD,
        'concurrently'       => Token::TYPE_TYPE_FUNC_NAME_KEYWORD,
        'configuration'      => Token::TYPE_UNRESERVED_KEYWORD,
        'conflict'           => Token::TYPE_UNRESERVED_KEYWORD,
        'connection'         => Token::TYPE_UNRESERVED_KEYWORD,
        'constraint'         => Token::TYPE_RESERVED_KEYWORD,
        'constraints'        => Token::TYPE_UNRESERVED_KEYWORD,
        'content'            => Token::TYPE_UNRESERVED_KEYWORD,
        'continue'           => Token::TYPE_UNRESERVED_KEYWORD,
        'conversion'         => Token::TYPE_UNRESERVED_KEYWORD,
        'copy'               => Token::TYPE_UNRESERVED_KEYWORD,
        'cost'               => Token::TYPE_UNRESERVED_KEYWORD,
        'create'             => Token::TYPE_RESERVED_KEYWORD,
        'cross'              => Token::TYPE_TYPE_FUNC_NAME_KEYWORD,
        'csv'                => Token::TYPE_UNRESERVED_KEYWORD,
        'cube'               => Token::TYPE_UNRESERVED_KEYWORD,
        'current'            => Token::TYPE_UNRESERVED_KEYWORD,
        'current_catalog'    => Token::TYPE_RESERVED_KEYWORD,
        'current_date'       => Token::TYPE_RESERVED_KEYWORD,
        'current_role'       => Token::TYPE_RESERVED_KEYWORD,
        'current_schema'     => Token::TYPE_TYPE_FUNC_NAME_KEYWORD,
        'current_time'       => Token::TYPE_RESERVED_KEYWORD,
        'current_timestamp'  => Token::TYPE_RESERVED_KEYWORD,
        'current_user'       => Token::TYPE_RESERVED_KEYWORD,
        'cursor'             => Token::TYPE_UNRESERVED_KEYWORD,
        'cycle'              => Token::TYPE_UNRESERVED_KEYWORD,
        'data'               => Token::TYPE_UNRESERVED_KEYWORD,
        'database'           => Token::TYPE_UNRESERVED_KEYWORD,
        'day'                => Token::TYPE_UNRESERVED_KEYWORD,
        'deallocate'         => Token::TYPE_UNRESERVED_KEYWORD,
        'dec'                => Token::TYPE_COL_NAME_KEYWORD,
        'decimal'            => Token::TYPE_COL_NAME_KEYWORD,
        'declare'            => Token::TYPE_UNRESERVED_KEYWORD,
        'default'            => Token::TYPE_RESERVED_KEYWORD,
        'defaults'           => Token::TYPE_UNRESERVED_KEYWORD,
        'deferrable'         => Token::TYPE_RESERVED_KEYWORD,
        'deferred'           => Token::TYPE_UNRESERVED_KEYWORD,
        'definer'            => Token::TYPE_UNRESERVED_KEYWORD,
        'delete'             => Token::TYPE_UNRESERVED_KEYWORD,
        'delimiter'          => Token::TYPE_UNRESERVED_KEYWORD,
        'delimiters'         => Token::TYPE_UNRESERVED_KEYWORD,
        'depends'            => Token::TYPE_UNRESERVED_KEYWORD,
        'desc'               => Token::TYPE_RESERVED_KEYWORD,
        'dictionary'         => Token::TYPE_UNRESERVED_KEYWORD,
        'disable'            => Token::TYPE_UNRESERVED_KEYWORD,
        'discard'            => Token::TYPE_UNRESERVED_KEYWORD,
        'distinct'           => Token::TYPE_RESERVED_KEYWORD,
        'do'                 => Token::TYPE_RESERVED_KEYWORD,
        'document'           => Token::TYPE_UNRESERVED_KEYWORD,
        'domain'             => Token::TYPE_UNRESERVED_KEYWORD,
        'double'             => Token::TYPE_UNRESERVED_KEYWORD,
        'drop'               => Token::TYPE_UNRESERVED_KEYWORD,
        'each'               => Token::TYPE_UNRESERVED_KEYWORD,
        'else'               => Token::TYPE_RESERVED_KEYWORD,
        'enable'             => Token::TYPE_UNRESERVED_KEYWORD,
        'encoding'           => Token::TYPE_UNRESERVED_KEYWORD,
        'encrypted'          => Token::TYPE_UNRESERVED_KEYWORD,
        'end'                => Token::TYPE_RESERVED_KEYWORD,
        'enum'               => Token::TYPE_UNRESERVED_KEYWORD,
        'escape'             => Token::TYPE_UNRESERVED_KEYWORD,
        'event'              => Token::TYPE_UNRESERVED_KEYWORD,
        'except'             => Token::TYPE_RESERVED_KEYWORD,
        'exclude'            => Token::TYPE_UNRESERVED_KEYWORD,
        'excluding'          => Token::TYPE_UNRESERVED_KEYWORD,
        'exclusive'          => Token::TYPE_UNRESERVED_KEYWORD,
        'execute'            => Token::TYPE_UNRESERVED_KEYWORD,
        'exists'             => Token::TYPE_COL_NAME_KEYWORD,
        'explain'            => Token::TYPE_UNRESERVED_KEYWORD,
        'extension'          => Token::TYPE_UNRESERVED_KEYWORD,
        'external'           => Token::TYPE_UNRESERVED_KEYWORD,
        'extract'            => Token::TYPE_COL_NAME_KEYWORD,
        'false'              => Token::TYPE_RESERVED_KEYWORD,
        'family'             => Token::TYPE_UNRESERVED_KEYWORD,
        'fetch'              => Token::TYPE_RESERVED_KEYWORD,
        'filter'             => Token::TYPE_UNRESERVED_KEYWORD,
        'first'              => Token::TYPE_UNRESERVED_KEYWORD,
        'float'              => Token::TYPE_COL_NAME_KEYWORD,
        'following'          => Token::TYPE_UNRESERVED_KEYWORD,
        'for'                => Token::TYPE_RESERVED_KEYWORD,
        'force'              => Token::TYPE_UNRESERVED_KEYWORD,
        'foreign'            => Token::TYPE_RESERVED_KEYWORD,
        'forward'            => Token::TYPE_UNRESERVED_KEYWORD,
        'freeze'             => Token::TYPE_TYPE_FUNC_NAME_KEYWORD,
        'from'               => Token::TYPE_RESERVED_KEYWORD,
        'full'               => Token::TYPE_TYPE_FUNC_NAME_KEYWORD,
        'function'           => Token::TYPE_UNRESERVED_KEYWORD,
        'functions'          => Token::TYPE_UNRESERVED_KEYWORD,
        'global'             => Token::TYPE_UNRESERVED_KEYWORD,
        'grant'              => Token::TYPE_RESERVED_KEYWORD,
        'granted'            => Token::TYPE_UNRESERVED_KEYWORD,
        'greatest'           => Token::TYPE_COL_NAME_KEYWORD,
        'group'              => Token::TYPE_RESERVED_KEYWORD,
        'grouping'           => Token::TYPE_COL_NAME_KEYWORD,
        'handler'            => Token::TYPE_UNRESERVED_KEYWORD,
        'having'             => Token::TYPE_RESERVED_KEYWORD,
        'header'             => Token::TYPE_UNRESERVED_KEYWORD,
        'hold'               => Token::TYPE_UNRESERVED_KEYWORD,
        'hour'               => Token::TYPE_UNRESERVED_KEYWORD,
        'identity'           => Token::TYPE_UNRESERVED_KEYWORD,
        'if'                 => Token::TYPE_UNRESERVED_KEYWORD,
        'ilike'              => Token::TYPE_TYPE_FUNC_NAME_KEYWORD,
        'immediate'          => Token::TYPE_UNRESERVED_KEYWORD,
        'immutable'          => Token::TYPE_UNRESERVED_KEYWORD,
        'implicit'           => Token::TYPE_UNRESERVED_KEYWORD,
        'import'             => Token::TYPE_UNRESERVED_KEYWORD,
        'in'                 => Token::TYPE_RESERVED_KEYWORD,
        'including'          => Token::TYPE_UNRESERVED_KEYWORD,
        'increment'          => Token::TYPE_UNRESERVED_KEYWORD,
        'index'              => Token::TYPE_UNRESERVED_KEYWORD,
        'indexes'            => Token::TYPE_UNRESERVED_KEYWORD,
        'inherit'            => Token::TYPE_UNRESERVED_KEYWORD,
        'inherits'           => Token::TYPE_UNRESERVED_KEYWORD,
        'initially'          => Token::TYPE_RESERVED_KEYWORD,
        'inline'             => Token::TYPE_UNRESERVED_KEYWORD,
        'inner'              => Token::TYPE_TYPE_FUNC_NAME_KEYWORD,
        'inout'              => Token::TYPE_COL_NAME_KEYWORD,
        'input'              => Token::TYPE_UNRESERVED_KEYWORD,
        'insensitive'        => Token::TYPE_UNRESERVED_KEYWORD,
        'insert'             => Token::TYPE_UNRESERVED_KEYWORD,
        'instead'            => Token::TYPE_UNRESERVED_KEYWORD,
        'int'                => Token::TYPE_COL_NAME_KEYWORD,
        'integer'            => Token::TYPE_COL_NAME_KEYWORD,
        'intersect'          => Token::TYPE_RESERVED_KEYWORD,
        'interval'           => Token::TYPE_COL_NAME_KEYWORD,
        'into'               => Token::TYPE_RESERVED_KEYWORD,
        'invoker'            => Token::TYPE_UNRESERVED_KEYWORD,
        'is'                 => Token::TYPE_TYPE_FUNC_NAME_KEYWORD,
        'isnull'             => Token::TYPE_TYPE_FUNC_NAME_KEYWORD,
        'isolation'          => Token::TYPE_UNRESERVED_KEYWORD,
        'join'               => Token::TYPE_TYPE_FUNC_NAME_KEYWORD,
        'key'                => Token::TYPE_UNRESERVED_KEYWORD,
        'label'              => Token::TYPE_UNRESERVED_KEYWORD,
        'language'           => Token::TYPE_UNRESERVED_KEYWORD,
        'large'              => Token::TYPE_UNRESERVED_KEYWORD,
        'last'               => Token::TYPE_UNRESERVED_KEYWORD,
        'lateral'            => Token::TYPE_RESERVED_KEYWORD,
        'leading'            => Token::TYPE_RESERVED_KEYWORD,
        'leakproof'          => Token::TYPE_UNRESERVED_KEYWORD,
        'least'              => Token::TYPE_COL_NAME_KEYWORD,
        'left'               => Token::TYPE_TYPE_FUNC_NAME_KEYWORD,
        'level'              => Token::TYPE_UNRESERVED_KEYWORD,
        'like'               => Token::TYPE_TYPE_FUNC_NAME_KEYWORD,
        'limit'              => Token::TYPE_RESERVED_KEYWORD,
        'listen'             => Token::TYPE_UNRESERVED_KEYWORD,
        'load'               => Token::TYPE_UNRESERVED_KEYWORD,
        'local'              => Token::TYPE_UNRESERVED_KEYWORD,
        'localtime'          => Token::TYPE_RESERVED_KEYWORD,
        'localtimestamp'     => Token::TYPE_RESERVED_KEYWORD,
        'location'           => Token::TYPE_UNRESERVED_KEYWORD,
        'lock'               => Token::TYPE_UNRESERVED_KEYWORD,
        'locked'             => Token::TYPE_UNRESERVED_KEYWORD,
        'logged'             => Token::TYPE_UNRESERVED_KEYWORD,
        'mapping'            => Token::TYPE_UNRESERVED_KEYWORD,
        'match'              => Token::TYPE_UNRESERVED_KEYWORD,
        'materialized'       => Token::TYPE_UNRESERVED_KEYWORD,
        'maxvalue'           => Token::TYPE_UNRESERVED_KEYWORD,
        'method'             => Token::TYPE_UNRESERVED_KEYWORD,
        'minute'             => Token::TYPE_UNRESERVED_KEYWORD,
        'minvalue'           => Token::TYPE_UNRESERVED_KEYWORD,
        'mode'               => Token::TYPE_UNRESERVED_KEYWORD,
        'month'              => Token::TYPE_UNRESERVED_KEYWORD,
        'move'               => Token::TYPE_UNRESERVED_KEYWORD,
        'name'               => Token::TYPE_UNRESERVED_KEYWORD,
        'names'              => Token::TYPE_UNRESERVED_KEYWORD,
        'national'           => Token::TYPE_COL_NAME_KEYWORD,
        'natural'            => Token::TYPE_TYPE_FUNC_NAME_KEYWORD,
        'nchar'              => Token::TYPE_COL_NAME_KEYWORD,
        'next'               => Token::TYPE_UNRESERVED_KEYWORD,
        'no'                 => Token::TYPE_UNRESERVED_KEYWORD,
        'none'               => Token::TYPE_COL_NAME_KEYWORD,
        'not'                => Token::TYPE_RESERVED_KEYWORD,
        'nothing'            => Token::TYPE_UNRESERVED_KEYWORD,
        'notify'             => Token::TYPE_UNRESERVED_KEYWORD,
        'notnull'            => Token::TYPE_TYPE_FUNC_NAME_KEYWORD,
        'nowait'             => Token::TYPE_UNRESERVED_KEYWORD,
        'null'               => Token::TYPE_RESERVED_KEYWORD,
        'nullif'             => Token::TYPE_COL_NAME_KEYWORD,
        'nulls'              => Token::TYPE_UNRESERVED_KEYWORD,
        'numeric'            => Token::TYPE_COL_NAME_KEYWORD,
        'object'             => Token::TYPE_UNRESERVED_KEYWORD,
        'of'                 => Token::TYPE_UNRESERVED_KEYWORD,
        'off'                => Token::TYPE_UNRESERVED_KEYWORD,
        'offset'             => Token::TYPE_RESERVED_KEYWORD,
        'oids'               => Token::TYPE_UNRESERVED_KEYWORD,
        'on'                 => Token::TYPE_RESERVED_KEYWORD,
        'only'               => Token::TYPE_RESERVED_KEYWORD,
        'operator'           => Token::TYPE_UNRESERVED_KEYWORD,
        'option'             => Token::TYPE_UNRESERVED_KEYWORD,
        'options'            => Token::TYPE_UNRESERVED_KEYWORD,
        'or'                 => Token::TYPE_RESERVED_KEYWORD,
        'order'              => Token::TYPE_RESERVED_KEYWORD,
        'ordinality'         => Token::TYPE_UNRESERVED_KEYWORD,
        'out'                => Token::TYPE_COL_NAME_KEYWORD,
        'outer'              => Token::TYPE_TYPE_FUNC_NAME_KEYWORD,
        'over'               => Token::TYPE_UNRESERVED_KEYWORD,
        'overlaps'           => Token::TYPE_TYPE_FUNC_NAME_KEYWORD,
        'overlay'            => Token::TYPE_COL_NAME_KEYWORD,
        'owned'              => Token::TYPE_UNRESERVED_KEYWORD,
        'owner'              => Token::TYPE_UNRESERVED_KEYWORD,
        'parallel'           => Token::TYPE_UNRESERVED_KEYWORD,
        'parser'             => Token::TYPE_UNRESERVED_KEYWORD,
        'partial'            => Token::TYPE_UNRESERVED_KEYWORD,
        'partition'          => Token::TYPE_UNRESERVED_KEYWORD,
        'passing'            => Token::TYPE_UNRESERVED_KEYWORD,
        'password'           => Token::TYPE_UNRESERVED_KEYWORD,
        'placing'            => Token::TYPE_RESERVED_KEYWORD,
        'plans'              => Token::TYPE_UNRESERVED_KEYWORD,
        'policy'             => Token::TYPE_UNRESERVED_KEYWORD,
        'position'           => Token::TYPE_COL_NAME_KEYWORD,
        'preceding'          => Token::TYPE_UNRESERVED_KEYWORD,
        'precision'          => Token::TYPE_COL_NAME_KEYWORD,
        'prepare'            => Token::TYPE_UNRESERVED_KEYWORD,
        'prepared'           => Token::TYPE_UNRESERVED_KEYWORD,
        'preserve'           => Token::TYPE_UNRESERVED_KEYWORD,
        'primary'            => Token::TYPE_RESERVED_KEYWORD,
        'prior'              => Token::TYPE_UNRESERVED_KEYWORD,
        'privileges'         => Token::TYPE_UNRESERVED_KEYWORD,
        'procedural'         => Token::TYPE_UNRESERVED_KEYWORD,
        'procedure'          => Token::TYPE_UNRESERVED_KEYWORD,
        'program'            => Token::TYPE_UNRESERVED_KEYWORD,
        'quote'              => Token::TYPE_UNRESERVED_KEYWORD,
        'range'              => Token::TYPE_UNRESERVED_KEYWORD,
        'read'               => Token::TYPE_UNRESERVED_KEYWORD,
        'real'               => Token::TYPE_COL_NAME_KEYWORD,
        'reassign'           => Token::TYPE_UNRESERVED_KEYWORD,
        'recheck'            => Token::TYPE_UNRESERVED_KEYWORD,
        'recursive'          => Token::TYPE_UNRESERVED_KEYWORD,
        'ref'                => Token::TYPE_UNRESERVED_KEYWORD,
        'references'         => Token::TYPE_RESERVED_KEYWORD,
        'refresh'            => Token::TYPE_UNRESERVED_KEYWORD,
        'reindex'            => Token::TYPE_UNRESERVED_KEYWORD,
        'relative'           => Token::TYPE_UNRESERVED_KEYWORD,
        'release'            => Token::TYPE_UNRESERVED_KEYWORD,
        'rename'             => Token::TYPE_UNRESERVED_KEYWORD,
        'repeatable'         => Token::TYPE_UNRESERVED_KEYWORD,
        'replace'            => Token::TYPE_UNRESERVED_KEYWORD,
        'replica'            => Token::TYPE_UNRESERVED_KEYWORD,
        'reset'              => Token::TYPE_UNRESERVED_KEYWORD,
        'restart'            => Token::TYPE_UNRESERVED_KEYWORD,
        'restrict'           => Token::TYPE_UNRESERVED_KEYWORD,
        'returning'          => Token::TYPE_RESERVED_KEYWORD,
        'returns'            => Token::TYPE_UNRESERVED_KEYWORD,
        'revoke'             => Token::TYPE_UNRESERVED_KEYWORD,
        'right'              => Token::TYPE_TYPE_FUNC_NAME_KEYWORD,
        'role'               => Token::TYPE_UNRESERVED_KEYWORD,
        'rollback'           => Token::TYPE_UNRESERVED_KEYWORD,
        'rollup'             => Token::TYPE_UNRESERVED_KEYWORD,
        'row'                => Token::TYPE_COL_NAME_KEYWORD,
        'rows'               => Token::TYPE_UNRESERVED_KEYWORD,
        'rule'               => Token::TYPE_UNRESERVED_KEYWORD,
        'savepoint'          => Token::TYPE_UNRESERVED_KEYWORD,
        'schema'             => Token::TYPE_UNRESERVED_KEYWORD,
        'scroll'             => Token::TYPE_UNRESERVED_KEYWORD,
        'search'             => Token::TYPE_UNRESERVED_KEYWORD,
        'second'             => Token::TYPE_UNRESERVED_KEYWORD,
        'security'           => Token::TYPE_UNRESERVED_KEYWORD,
        'select'             => Token::TYPE_RESERVED_KEYWORD,
        'sequence'           => Token::TYPE_UNRESERVED_KEYWORD,
        'sequences'          => Token::TYPE_UNRESERVED_KEYWORD,
        'serializable'       => Token::TYPE_UNRESERVED_KEYWORD,
        'server'             => Token::TYPE_UNRESERVED_KEYWORD,
        'session'            => Token::TYPE_UNRESERVED_KEYWORD,
        'session_user'       => Token::TYPE_RESERVED_KEYWORD,
        'set'                => Token::TYPE_UNRESERVED_KEYWORD,
        'setof'              => Token::TYPE_COL_NAME_KEYWORD,
        'sets'               => Token::TYPE_UNRESERVED_KEYWORD,
        'share'              => Token::TYPE_UNRESERVED_KEYWORD,
        'show'               => Token::TYPE_UNRESERVED_KEYWORD,
        'similar'            => Token::TYPE_TYPE_FUNC_NAME_KEYWORD,
        'simple'             => Token::TYPE_UNRESERVED_KEYWORD,
        'skip'               => Token::TYPE_UNRESERVED_KEYWORD,
        'smallint'           => Token::TYPE_COL_NAME_KEYWORD,
        'snapshot'           => Token::TYPE_UNRESERVED_KEYWORD,
        'some'               => Token::TYPE_RESERVED_KEYWORD,
        'sql'                => Token::TYPE_UNRESERVED_KEYWORD,
        'stable'             => Token::TYPE_UNRESERVED_KEYWORD,
        'standalone'         => Token::TYPE_UNRESERVED_KEYWORD,
        'start'              => Token::TYPE_UNRESERVED_KEYWORD,
        'statement'          => Token::TYPE_UNRESERVED_KEYWORD,
        'statistics'         => Token::TYPE_UNRESERVED_KEYWORD,
        'stdin'              => Token::TYPE_UNRESERVED_KEYWORD,
        'stdout'             => Token::TYPE_UNRESERVED_KEYWORD,
        'storage'            => Token::TYPE_UNRESERVED_KEYWORD,
        'strict'             => Token::TYPE_UNRESERVED_KEYWORD,
        'strip'              => Token::TYPE_UNRESERVED_KEYWORD,
        'substring'          => Token::TYPE_COL_NAME_KEYWORD,
        'symmetric'          => Token::TYPE_RESERVED_KEYWORD,
        'sysid'              => Token::TYPE_UNRESERVED_KEYWORD,
        'system'             => Token::TYPE_UNRESERVED_KEYWORD,
        'table'              => Token::TYPE_RESERVED_KEYWORD,
        'tables'             => Token::TYPE_UNRESERVED_KEYWORD,
        'tablesample'        => Token::TYPE_TYPE_FUNC_NAME_KEYWORD,
        'tablespace'         => Token::TYPE_UNRESERVED_KEYWORD,
        'temp'               => Token::TYPE_UNRESERVED_KEYWORD,
        'template'           => Token::TYPE_UNRESERVED_KEYWORD,
        'temporary'          => Token::TYPE_UNRESERVED_KEYWORD,
        'text'               => Token::TYPE_UNRESERVED_KEYWORD,
        'then'               => Token::TYPE_RESERVED_KEYWORD,
        'time'               => Token::TYPE_COL_NAME_KEYWORD,
        'timestamp'          => Token::TYPE_COL_NAME_KEYWORD,
        'to'                 => Token::TYPE_RESERVED_KEYWORD,
        'trailing'           => Token::TYPE_RESERVED_KEYWORD,
        'transaction'        => Token::TYPE_UNRESERVED_KEYWORD,
        'transform'          => Token::TYPE_UNRESERVED_KEYWORD,
        'treat'              => Token::TYPE_COL_NAME_KEYWORD,
        'trigger'            => Token::TYPE_UNRESERVED_KEYWORD,
        'trim'               => Token::TYPE_COL_NAME_KEYWORD,
        'true'               => Token::TYPE_RESERVED_KEYWORD,
        'truncate'           => Token::TYPE_UNRESERVED_KEYWORD,
        'trusted'            => Token::TYPE_UNRESERVED_KEYWORD,
        'type'               => Token::TYPE_UNRESERVED_KEYWORD,
        'types'              => Token::TYPE_UNRESERVED_KEYWORD,
        'unbounded'          => Token::TYPE_UNRESERVED_KEYWORD,
        'uncommitted'        => Token::TYPE_UNRESERVED_KEYWORD,
        'unencrypted'        => Token::TYPE_UNRESERVED_KEYWORD,
        'union'              => Token::TYPE_RESERVED_KEYWORD,
        'unique'             => Token::TYPE_RESERVED_KEYWORD,
        'unknown'            => Token::TYPE_UNRESERVED_KEYWORD,
        'unlisten'           => Token::TYPE_UNRESERVED_KEYWORD,
        'unlogged'           => Token::TYPE_UNRESERVED_KEYWORD,
        'until'              => Token::TYPE_UNRESERVED_KEYWORD,
        'update'             => Token::TYPE_UNRESERVED_KEYWORD,
        'user'               => Token::TYPE_RESERVED_KEYWORD,
        'using'              => Token::TYPE_RESERVED_KEYWORD,
        'vacuum'             => Token::TYPE_UNRESERVED_KEYWORD,
        'valid'              => Token::TYPE_UNRESERVED_KEYWORD,
        'validate'           => Token::TYPE_UNRESERVED_KEYWORD,
        'validator'          => Token::TYPE_UNRESERVED_KEYWORD,
        'value'              => Token::TYPE_UNRESERVED_KEYWORD,
        'values'             => Token::TYPE_COL_NAME_KEYWORD,
        'varchar'            => Token::TYPE_COL_NAME_KEYWORD,
        'variadic'           => Token::TYPE_RESERVED_KEYWORD,
        'varying'            => Token::TYPE_UNRESERVED_KEYWORD,
        'verbose'            => Token::TYPE_TYPE_FUNC_NAME_KEYWORD,
        'version'            => Token::TYPE_UNRESERVED_KEYWORD,
        'view'               => Token::TYPE_UNRESERVED_KEYWORD,
        'views'              => Token::TYPE_UNRESERVED_KEYWORD,
        'volatile'           => Token::TYPE_UNRESERVED_KEYWORD,
        'when'               => Token::TYPE_RESERVED_KEYWORD,
        'where'              => Token::TYPE_RESERVED_KEYWORD,
        'whitespace'         => Token::TYPE_UNRESERVED_KEYWORD,
        'window'             => Token::TYPE_RESERVED_KEYWORD,
        'with'               => Token::TYPE_RESERVED_KEYWORD,
        'within'             => Token::TYPE_UNRESERVED_KEYWORD,
        'without'            => Token::TYPE_UNRESERVED_KEYWORD,
        'work'               => Token::TYPE_UNRESERVED_KEYWORD,
        'wrapper'            => Token::TYPE_UNRESERVED_KEYWORD,
        'write'              => Token::TYPE_UNRESERVED_KEYWORD,
        'xml'                => Token::TYPE_UNRESERVED_KEYWORD,
        'xmlattributes'      => Token::TYPE_COL_NAME_KEYWORD,
        'xmlconcat'          => Token::TYPE_COL_NAME_KEYWORD,
        'xmlelement'         => Token::TYPE_COL_NAME_KEYWORD,
        'xmlexists'          => Token::TYPE_COL_NAME_KEYWORD,
        'xmlforest'          => Token::TYPE_COL_NAME_KEYWORD,
        'xmlparse'           => Token::TYPE_COL_NAME_KEYWORD,
        'xmlpi'              => Token::TYPE_COL_NAME_KEYWORD,
        'xmlroot'            => Token::TYPE_COL_NAME_KEYWORD,
        'xmlserialize'       => Token::TYPE_COL_NAME_KEYWORD,
        'year'               => Token::TYPE_UNRESERVED_KEYWORD,
        'yes'                => Token::TYPE_UNRESERVED_KEYWORD,
        'zone'               => Token::TYPE_UNRESERVED_KEYWORD
    );


    protected $string;
    protected $position;
    protected $length;
    protected $tokens;
    protected $options = array();
    protected $mbEncoding;

    /**
     * Checks whether a given string is a recognized keyword
     *
     * @param string $string
     * @return bool
     */
    public static function isKeyword($string)
    {
        return array_key_exists($string, self::$keywords);
    }

    /**
     * Stores a token in the array
     *
     * @param string $value     Token value
     * @param int    $type      Token type, one of Token::TYPE_* constants
     * @param int    $position  Position in input
     */
    protected function pushToken($value, $type, $position)
    {
        $this->tokens[] = new Token($type, $value, $position);
    }

    /**
     * Constructor, sets options for lexer
     *
     * Possible options are
     *  'standard_conforming_strings' - the same meaning as the postgresql.conf parameter. When true (default),
     *      then backslashes in '...' strings are treated literally, when false they are treated as escape
     *      characters.
     *  'ascii_only_downcasing' - when true only ASCII letters in unquoted identifiers will be converted to
     *      lower case, when false all letters (according to current mbstring encoding / locale). Only should
     *      be set to true for single-byte encodings (see changelog for Postgres releases @ 2013-10-10)!
     *
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $this->options  = array_merge(array(
            'standard_conforming_strings' => true,
            'ascii_only_downcasing'       => true
        ), $options);
    }

    /**
     * Tokenizes the input string
     *
     * @param string $sql
     * @return TokenStream
     * @throws exceptions\NotImplementedException
     * @throws exceptions\SyntaxException
     */
    public function tokenize($sql)
    {
        if (extension_loaded('mbstring')) {
            $this->mbEncoding = mb_internal_encoding();
            if (2 & ini_get('mbstring.func_overload')) {
                mb_internal_encoding('8bit');
            }
        }

        $this->string   = $sql;
        $this->position = strspn($this->string, " \r\n\t\f", 0);
        $this->length   = strlen($sql);
        $this->tokens   = array();

        try {
            $this->doTokenize();

        } catch (\Exception $e) {
            if (2 & ini_get('mbstring.func_overload')) {
                mb_internal_encoding($this->mbEncoding);
            }
            throw $e;
        }

        if (2 & ini_get('mbstring.func_overload')) {
            mb_internal_encoding($this->mbEncoding);
        }

        $this->pushToken('', Token::TYPE_EOF, $this->position);

        return new TokenStream($this->tokens, $this->string);
    }

    /**
     * Does the actual tokenizing work
     *
     * @throws exceptions\SyntaxException
     * @throws exceptions\NotImplementedException
     */
    protected function doTokenize()
    {
        while ($this->position < $this->length) {
            $char     = $this->string[$this->position];
            $nextChar = $this->position + 1 < $this->length ? $this->string[$this->position + 1] : null;
            if ('-' === $char && '-' === $nextChar) {
                // single line comment, skip until newline
                $this->position += strcspn($this->string, "\r\n", $this->position);

            } elseif ('/' === $char && '*' === $nextChar) {
                // multiline comment, skip
                if (!preg_match(
                    '!/\* ( (?>[^/*]+ | /[^*] | \*[^/] ) | (?R) )* \*/!Ax',
                    $this->string, $m, 0, $this->position
                )) {
                    throw exceptions\SyntaxException::atPosition('Unterminated /* comment', $this->string, $this->position);
                }
                $this->position += strlen($m[0]);

            } elseif ('"' === $char) {
                $this->lexDoubleQuoted();

            } elseif (strspn($char, 'bBeEnNxX') && "'" === $nextChar) {
                $this->lexString(strtolower($char));

            } elseif ("'" === $char) {
                $this->lexString();

            } elseif ('$' === $char) {
                if (preg_match(
                    '/\$([A-Za-z\x80-\xFF_][A-Za-z\x80-\xFF_0-9]*)?\$/A',
                    $this->string, $m, 0, $this->position
                )) {
                    $this->lexDollarQuoted($m[0]);

                } elseif (preg_match('/\$(\d+)/A', $this->string, $m, 0, $this->position)) {
                    $this->pushToken($m[1], Token::TYPE_POSITIONAL_PARAM, $this->position);
                    $this->position += strlen($m[0]);

                } else {
                    throw exceptions\SyntaxException::atPosition("Unexpected '\$'", $this->string, $this->position);
                }


            } elseif (':' === $char) {
                if (':' === $nextChar) {
                    $this->pushToken('::', Token::TYPE_TYPECAST, $this->position);
                    $this->position += 2;
                } elseif ('=' === $nextChar) {
                    $this->pushToken(':=', Token::TYPE_COLON_EQUALS, $this->position);
                    $this->position += 2;
                } elseif (preg_match(
                    '/:([A-Za-z\x80-\xFF_][A-Za-z\x80-\xFF_0-9]*)/A',
                    $this->string, $m, 0, $this->position
                )) {
                    $this->pushToken($m[1], Token::TYPE_NAMED_PARAM, $this->position);
                    $this->position += strlen($m[0]);
                } else {
                    $this->pushToken(':', Token::TYPE_SPECIAL_CHAR, $this->position++);
                }

            } elseif ('.' === $char) {
                if ('.' === $nextChar) {
                    // Double dot is used only in PL/PgSQL. We don't parse that.
                    throw exceptions\SyntaxException::atPosition("Unexpected '..'", $this->string, $this->position);
                } elseif (ctype_digit($nextChar)) {
                    $this->lexNumeric();
                } else {
                    $this->pushToken('.', Token::TYPE_SPECIAL_CHAR, $this->position++);
                }

            } elseif ('=' === $char && '>' === $nextChar) {
                $this->pushToken('=>', Token::TYPE_EQUALS_GREATER, $this->position);
                $this->position += 2;

            } elseif ('=' === $nextChar && ('<' === $char || '>' === $char || '!' === $char)
                      || '>' === $nextChar && '<' === $char
            ) {
                $this->pushToken($char . $nextChar, Token::TYPE_INEQUALITY, $this->position);
                $this->position += 2;

            } elseif (ctype_digit($char)) {
                $this->lexNumeric();

            } elseif (strspn($char, self::CHARS_OPERATOR)) {
                $this->lexOperator();

            } elseif (strspn($char, self::CHARS_SPECIAL)) {
                $this->pushToken($char, Token::TYPE_SPECIAL_CHAR, $this->position++);

            } elseif (('u' === $char || 'U' === $char)
                      && preg_match('/[uU]&["\']/A', $this->string, $m, 0, $this->position)
            ) {
                throw new exceptions\NotImplementedException('Support for unicode escapes not implemented yet');

            } else {
                $this->lexIdentifier();
            }

            // skip whitespace
            $this->position += strspn($this->string, " \r\n\t\f", $this->position);
        }
    }

    /**
     * Processes a double-quoted identifier
     *
     * @throws exceptions\SyntaxException
     */
    protected function lexDoubleQuoted()
    {
        if (!preg_match('/" ( (?>[^"]+ | "")* ) "/Ax', $this->string, $m, 0, $this->position)) {
            throw exceptions\SyntaxException::atPosition('Unterminated quoted identifier', $this->string, $this->position);
        } elseif (!strlen($m[1])) {
            throw exceptions\SyntaxException::atPosition('Zero-length quoted identifier', $this->string, $this->position);
        }
        $this->pushToken(strtr($m[1], array('""' => '"')), Token::TYPE_IDENTIFIER, $this->position);
        $this->position += strlen($m[0]);
    }

    /**
     * Processes a dollar-quoted string
     *
     * @param string $delimiter
     * @throws exceptions\SyntaxException
     */
    protected function lexDollarQuoted($delimiter)
    {
        $delimiterLength = strlen($delimiter);
        if (false === ($pos = strpos($this->string, $delimiter, $this->position + $delimiterLength))) {
            throw exceptions\SyntaxException::atPosition('Unterminated dollar-quoted string', $this->string, $this->position);
        }
        $this->pushToken(
            substr($this->string, $this->position + $delimiterLength, $pos - $this->position - $delimiterLength),
            Token::TYPE_STRING, $this->position
        );
        $this->position = $pos + $delimiterLength;
    }

    /**
     * Processes a single-quoted literal
     *
     * @param string $char Character before quote that defines string type, one of 'b', 'e', 'x', 'n'
     * @throws exceptions\SyntaxException
     */
    protected function lexString($char = '')
    {
        $realPosition   = $this->position;
        $type           = Token::TYPE_STRING;
        $regexNoQuotes  = "'[^']*'";
        $regexSlashes   = "' ( (?>[^'\\\\]+ | '' | \\\\.)* ) '";
        $regexNoSlashes = "' ( (?>[^']+ | '')* ) '";
        switch ($char) {
        case 'b':
            $type  = Token::TYPE_BINARY_STRING;
            $regex = $regexNoQuotes;
            break;

        case 'e':
            $regex = $regexSlashes;
            break;

        case 'x':
            $regex = $regexNoQuotes;
            $type  = Token::TYPE_HEX_STRING;
            break;

        case 'n':
            $type  = Token::TYPE_NCHAR_STRING;
            // fall-through is intentional here

        default:
            $regex = $this->options['standard_conforming_strings'] ? $regexNoSlashes : $regexSlashes;
        }

        if (!preg_match("/{$regex}/Ax", $this->string, $m, 0, $this->position + ('' === $char ? 0 : 1))) {
            throw exceptions\SyntaxException::atPosition('Unterminated string literal', $this->string, $this->position);
        }

        $value  = '';
        $concat = '(?: [ \t\f] | --[^\n\r]* )* [\n\r] (?: [ \t\n\r\f]+ | --[^\n\r]*[\n\r] )*' . $regex;
        do {
            if ($regex === $regexNoQuotes) {
                $value .= $m[1];
            } elseif ($regex === $regexNoSlashes) {
                $value .= strtr($m[1], array("''" => "'"));
            } else {
                $value .= strtr(stripcslashes($m[1]), array("''" => "'"));
            }
            $this->position += ('' === $char ? 0 : 1) + strlen($m[0]);
            $char            = '';
        } while (preg_match("/{$concat}/Ax", $this->string, $m, 0, $this->position));

        $this->pushToken($value, $type, $realPosition);
    }

    /**
     * Processes a numeric literal
     */
    protected function lexNumeric()
    {
        // this will always match as lexNumeric is called on either 'digit' or '.digit'
        preg_match(
            '/(\d+ (\.\d+|\.(?!.))? | \.\d+) ( [eE][+-]\d+ )?/Ax',
            $this->string, $m, 0, $this->position
        );
        if (ctype_digit($m[0])) {
            $this->pushToken($m[0], Token::TYPE_INTEGER, $this->position);
        } else {
            $this->pushToken($m[0], Token::TYPE_FLOAT, $this->position);
        }
        $this->position += strlen($m[0]);
    }

    /**
     * Processes an operator
     */
    protected function lexOperator()
    {
        $length   = strspn($this->string, self::CHARS_OPERATOR, $this->position);
        $operator = substr($this->string, $this->position, $length);
        if ($commentSingle = strpos($operator, '--')) {
            $length = min($length, $commentSingle);
        }
        if ($commentMulti = strpos($operator, '/*')) {
            $length = min($length, $commentMulti);
        }
        while ($length > 1
               && ('+' === $operator[$length - 1] || '-' === $operator[$length - 1])
        ) {
            for ($i = $length - 2; $i >= 0; $i--) {
                if (strspn($operator[$i], "~!@#^&|`?%")) {
                    break 2;
                }
            }
            $length--;
        }

        $operator = substr($operator, 0, $length);
        if (1 === $length && strspn($operator, self::CHARS_SPECIAL)) {
            $this->pushToken($operator, Token::TYPE_SPECIAL_CHAR, $this->position++);
            return;
        }

        $this->pushToken($operator, Token::TYPE_OPERATOR, $this->position);
        $this->position += $length;
    }

    /**
     * Processes an identifier or a keyword
     *
     * @throws exceptions\SyntaxException
     */
    protected function lexIdentifier()
    {
        if (!preg_match(
            '/[A-Za-z\x80-\xff_][A-Za-z\x80-\xff_0-9\$]*/A',
            $this->string, $m, 0, $this->position
        )) {
            throw exceptions\SyntaxException::atPosition(
                "Unexpected '{$this->string[$this->position]}'", $this->string, $this->position
            );
        }

        // ASCII-only downcasing
        $lowcase = strtr($m[0], 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
        if (isset(self::$keywords[$lowcase])) {
            $this->pushToken($lowcase, self::$keywords[$lowcase], $this->position);

        } else {
            if (!$this->options['ascii_only_downcasing']) {
                if ($this->mbEncoding) {
                    $lowcase = mb_strtolower($lowcase, $this->mbEncoding);
                } else {
                    $lowcase = strtolower($lowcase);
                }
            }
            $this->pushToken($lowcase, Token::TYPE_IDENTIFIER, $this->position);
        }

        $this->position += strlen($m[0]);
    }
}