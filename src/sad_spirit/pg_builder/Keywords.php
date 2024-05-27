<?php

/**
 * Query builder for Postgres backed by SQL parser
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-builder/master/LICENSE
 *
 * @package   sad_spirit\pg_builder
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-builder
 */

declare(strict_types=1);

namespace sad_spirit\pg_builder;

/**
 * Contains a list of all PostgreSQL keywords
 */
final class Keywords
{
    /**
     * List of all keywords recognized by PostgreSQL
     * Source: src/include/parser/kwlist.h
     *
     * First array key in a value array is a type for a Token corresponding to that keyword,
     * second array key specifies whether the keyword can be used as a column label without AS.
     *
     * @var array
     */
    public const LIST = [
        'abort'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'absent'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'absolute'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'access'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'action'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'add'                => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'admin'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'after'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'aggregate'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'all'                => [Token::TYPE_RESERVED_KEYWORD,       true],
        'also'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'alter'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'always'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'analyse'            => [Token::TYPE_RESERVED_KEYWORD,       true],
        'analyze'            => [Token::TYPE_RESERVED_KEYWORD,       true],
        'and'                => [Token::TYPE_RESERVED_KEYWORD,       true],
        'any'                => [Token::TYPE_RESERVED_KEYWORD,       true],
        'array'              => [Token::TYPE_RESERVED_KEYWORD,       false],
        'as'                 => [Token::TYPE_RESERVED_KEYWORD,       false],
        'asc'                => [Token::TYPE_RESERVED_KEYWORD,       true],
        'asensitive'         => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'assertion'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'assignment'         => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'asymmetric'         => [Token::TYPE_RESERVED_KEYWORD,       true],
        'at'                 => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'atomic'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'attach'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'attribute'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'authorization'      => [Token::TYPE_TYPE_FUNC_NAME_KEYWORD, true],
        'backward'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'before'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'begin'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'between'            => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'bigint'             => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'binary'             => [Token::TYPE_TYPE_FUNC_NAME_KEYWORD, true],
        'bit'                => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'boolean'            => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'both'               => [Token::TYPE_RESERVED_KEYWORD,       true],
        'breadth'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'by'                 => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'cache'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'call'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'called'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'cascade'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'cascaded'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'case'               => [Token::TYPE_RESERVED_KEYWORD,       true],
        'cast'               => [Token::TYPE_RESERVED_KEYWORD,       true],
        'catalog'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'chain'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'char'               => [Token::TYPE_COL_NAME_KEYWORD,       false],
        'character'          => [Token::TYPE_COL_NAME_KEYWORD,       false],
        'characteristics'    => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'check'              => [Token::TYPE_RESERVED_KEYWORD,       true],
        'checkpoint'         => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'class'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'close'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'cluster'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'coalesce'           => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'collate'            => [Token::TYPE_RESERVED_KEYWORD,       true],
        'collation'          => [Token::TYPE_TYPE_FUNC_NAME_KEYWORD, true],
        'column'             => [Token::TYPE_RESERVED_KEYWORD,       true],
        'columns'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'comment'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'comments'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'commit'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'committed'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'compression'        => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'concurrently'       => [Token::TYPE_TYPE_FUNC_NAME_KEYWORD, true],
        'configuration'      => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'conflict'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'connection'         => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'constraint'         => [Token::TYPE_RESERVED_KEYWORD,       true],
        'constraints'        => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'content'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'continue'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'conversion'         => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'copy'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'cost'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'create'             => [Token::TYPE_RESERVED_KEYWORD,       false],
        'cross'              => [Token::TYPE_TYPE_FUNC_NAME_KEYWORD, true],
        'csv'                => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'cube'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'current'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'current_catalog'    => [Token::TYPE_RESERVED_KEYWORD,       true],
        'current_date'       => [Token::TYPE_RESERVED_KEYWORD,       true],
        'current_role'       => [Token::TYPE_RESERVED_KEYWORD,       true],
        'current_schema'     => [Token::TYPE_TYPE_FUNC_NAME_KEYWORD, true],
        'current_time'       => [Token::TYPE_RESERVED_KEYWORD,       true],
        'current_timestamp'  => [Token::TYPE_RESERVED_KEYWORD,       true],
        'current_user'       => [Token::TYPE_RESERVED_KEYWORD,       true],
        'cursor'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'cycle'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'data'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'database'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'day'                => [Token::TYPE_UNRESERVED_KEYWORD,     false],
        'deallocate'         => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'dec'                => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'decimal'            => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'declare'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'default'            => [Token::TYPE_RESERVED_KEYWORD,       true],
        'defaults'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'deferrable'         => [Token::TYPE_RESERVED_KEYWORD,       true],
        'deferred'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'definer'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'delete'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'delimiter'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'delimiters'         => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'depends'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'depth'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'desc'               => [Token::TYPE_RESERVED_KEYWORD,       true],
        'detach'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'dictionary'         => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'disable'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'discard'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'distinct'           => [Token::TYPE_RESERVED_KEYWORD,       true],
        'do'                 => [Token::TYPE_RESERVED_KEYWORD,       true],
        'document'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'domain'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'double'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'drop'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'each'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'else'               => [Token::TYPE_RESERVED_KEYWORD,       true],
        'enable'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'encoding'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'encrypted'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'end'                => [Token::TYPE_RESERVED_KEYWORD,       true],
        'enum'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'escape'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'event'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'except'             => [Token::TYPE_RESERVED_KEYWORD,       false],
        'exclude'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'excluding'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'exclusive'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'execute'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'exists'             => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'explain'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'expression'         => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'extension'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'external'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'extract'            => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'false'              => [Token::TYPE_RESERVED_KEYWORD,       true],
        'family'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'fetch'              => [Token::TYPE_RESERVED_KEYWORD,       false],
        'filter'             => [Token::TYPE_UNRESERVED_KEYWORD,     false],
        'finalize'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'first'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'float'              => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'following'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'for'                => [Token::TYPE_RESERVED_KEYWORD,       false],
        'force'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'foreign'            => [Token::TYPE_RESERVED_KEYWORD,       true],
        'format'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'forward'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'freeze'             => [Token::TYPE_TYPE_FUNC_NAME_KEYWORD, true],
        'from'               => [Token::TYPE_RESERVED_KEYWORD,       false],
        'full'               => [Token::TYPE_TYPE_FUNC_NAME_KEYWORD, true],
        'function'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'functions'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'generated'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'global'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'grant'              => [Token::TYPE_RESERVED_KEYWORD,       false],
        'granted'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'greatest'           => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'group'              => [Token::TYPE_RESERVED_KEYWORD,       false],
        'grouping'           => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'groups'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'handler'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'having'             => [Token::TYPE_RESERVED_KEYWORD,       false],
        'header'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'hold'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'hour'               => [Token::TYPE_UNRESERVED_KEYWORD,     false],
        'identity'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'if'                 => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'ilike'              => [Token::TYPE_TYPE_FUNC_NAME_KEYWORD, true],
        'immediate'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'immutable'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'implicit'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'import'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'in'                 => [Token::TYPE_RESERVED_KEYWORD,       true],
        'include'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'including'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'increment'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'indent'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'index'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'indexes'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'inherit'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'inherits'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'initially'          => [Token::TYPE_RESERVED_KEYWORD,       true],
        'inline'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'inner'              => [Token::TYPE_TYPE_FUNC_NAME_KEYWORD, true],
        'inout'              => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'input'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'insensitive'        => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'insert'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'instead'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'int'                => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'integer'            => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'intersect'          => [Token::TYPE_RESERVED_KEYWORD,       false],
        'interval'           => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'into'               => [Token::TYPE_RESERVED_KEYWORD,       false],
        'invoker'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'is'                 => [Token::TYPE_TYPE_FUNC_NAME_KEYWORD, true],
        'isnull'             => [Token::TYPE_TYPE_FUNC_NAME_KEYWORD, false],
        'isolation'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'join'               => [Token::TYPE_TYPE_FUNC_NAME_KEYWORD, true],
        'json'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'json_array'         => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'json_arrayagg'      => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'json_object'        => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'json_objectagg'     => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'key'                => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'keys'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'label'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'language'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'large'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'last'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'lateral'            => [Token::TYPE_RESERVED_KEYWORD,       true],
        'leading'            => [Token::TYPE_RESERVED_KEYWORD,       true],
        'leakproof'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'least'              => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'left'               => [Token::TYPE_TYPE_FUNC_NAME_KEYWORD, true],
        'level'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'like'               => [Token::TYPE_TYPE_FUNC_NAME_KEYWORD, true],
        'limit'              => [Token::TYPE_RESERVED_KEYWORD,       false],
        'listen'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'load'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'local'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'localtime'          => [Token::TYPE_RESERVED_KEYWORD,       true],
        'localtimestamp'     => [Token::TYPE_RESERVED_KEYWORD,       true],
        'location'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'lock'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'locked'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'logged'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'mapping'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'match'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'matched'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'materialized'       => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'maxvalue'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'merge'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'method'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'minute'             => [Token::TYPE_UNRESERVED_KEYWORD,     false],
        'minvalue'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'mode'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'month'              => [Token::TYPE_UNRESERVED_KEYWORD,     false],
        'move'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'name'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'names'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'national'           => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'natural'            => [Token::TYPE_TYPE_FUNC_NAME_KEYWORD, true],
        'nchar'              => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'new'                => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'next'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'nfc'                => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'nfd'                => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'nfkc'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'nfkd'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'no'                 => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'none'               => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'normalize'          => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'normalized'         => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'not'                => [Token::TYPE_RESERVED_KEYWORD,       true],
        'nothing'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'notify'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'notnull'            => [Token::TYPE_TYPE_FUNC_NAME_KEYWORD, false],
        'nowait'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'null'               => [Token::TYPE_RESERVED_KEYWORD,       true],
        'nullif'             => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'nulls'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'numeric'            => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'object'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'of'                 => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'off'                => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'offset'             => [Token::TYPE_RESERVED_KEYWORD,       false],
        'oids'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'old'                => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'on'                 => [Token::TYPE_RESERVED_KEYWORD,       false],
        'only'               => [Token::TYPE_RESERVED_KEYWORD,       true],
        'operator'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'option'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'options'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'or'                 => [Token::TYPE_RESERVED_KEYWORD,       true],
        'order'              => [Token::TYPE_RESERVED_KEYWORD,       false],
        'ordinality'         => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'others'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'out'                => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'outer'              => [Token::TYPE_TYPE_FUNC_NAME_KEYWORD, true],
        'over'               => [Token::TYPE_UNRESERVED_KEYWORD,     false],
        'overlaps'           => [Token::TYPE_TYPE_FUNC_NAME_KEYWORD, false],
        'overlay'            => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'overriding'         => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'owned'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'owner'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'parallel'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'parameter'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'parser'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'partial'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'partition'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'passing'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'password'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'placing'            => [Token::TYPE_RESERVED_KEYWORD,       true],
        'plans'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'policy'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'position'           => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'preceding'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'precision'          => [Token::TYPE_COL_NAME_KEYWORD,       false],
        'prepare'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'prepared'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'preserve'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'primary'            => [Token::TYPE_RESERVED_KEYWORD,       true],
        'prior'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'privileges'         => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'procedural'         => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'procedure'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'procedures'         => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'program'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'publication'        => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'quote'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'range'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'read'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'real'               => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'reassign'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'recheck'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'recursive'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'ref'                => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'references'         => [Token::TYPE_RESERVED_KEYWORD,       true],
        'referencing'        => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'refresh'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'reindex'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'relative'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'release'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'rename'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'repeatable'         => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'replace'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'replica'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'reset'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'restart'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'restrict'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'return'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'returning'          => [Token::TYPE_RESERVED_KEYWORD,       false],
        'returns'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'revoke'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'right'              => [Token::TYPE_TYPE_FUNC_NAME_KEYWORD, true],
        'role'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'rollback'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'rollup'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'routine'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'routines'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'row'                => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'rows'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'rule'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'savepoint'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'scalar'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'schema'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'schemas'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'scroll'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'search'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'second'             => [Token::TYPE_UNRESERVED_KEYWORD,     false],
        'security'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'select'             => [Token::TYPE_RESERVED_KEYWORD,       true],
        'sequence'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'sequences'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'serializable'       => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'server'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'session'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'session_user'       => [Token::TYPE_RESERVED_KEYWORD,       true],
        'set'                => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'setof'              => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'sets'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'share'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'show'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'similar'            => [Token::TYPE_TYPE_FUNC_NAME_KEYWORD, true],
        'simple'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'skip'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'smallint'           => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'snapshot'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'some'               => [Token::TYPE_RESERVED_KEYWORD,       true],
        'sql'                => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'stable'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'standalone'         => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'start'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'statement'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'statistics'         => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'stdin'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'stdout'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'storage'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'stored'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'strict'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'strip'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'subscription'       => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'substring'          => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'support'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'symmetric'          => [Token::TYPE_RESERVED_KEYWORD,       true],
        'sysid'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'system'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'system_user'        => [Token::TYPE_RESERVED_KEYWORD,       true],
        'table'              => [Token::TYPE_RESERVED_KEYWORD,       true],
        'tables'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'tablesample'        => [Token::TYPE_TYPE_FUNC_NAME_KEYWORD, true],
        'tablespace'         => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'temp'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'template'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'temporary'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'text'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'then'               => [Token::TYPE_RESERVED_KEYWORD,       true],
        'ties'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'time'               => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'timestamp'          => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'to'                 => [Token::TYPE_RESERVED_KEYWORD,       false],
        'trailing'           => [Token::TYPE_RESERVED_KEYWORD,       true],
        'transaction'        => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'transform'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'treat'              => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'trigger'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'trim'               => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'true'               => [Token::TYPE_RESERVED_KEYWORD,       true],
        'truncate'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'trusted'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'type'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'types'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'uescape'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'unbounded'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'uncommitted'        => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'unencrypted'        => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'union'              => [Token::TYPE_RESERVED_KEYWORD,       false],
        'unique'             => [Token::TYPE_RESERVED_KEYWORD,       true],
        'unknown'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'unlisten'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'unlogged'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'until'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'update'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'user'               => [Token::TYPE_RESERVED_KEYWORD,       true],
        'using'              => [Token::TYPE_RESERVED_KEYWORD,       true],
        'vacuum'             => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'valid'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'validate'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'validator'          => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'value'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'values'             => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'varchar'            => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'variadic'           => [Token::TYPE_RESERVED_KEYWORD,       true],
        'varying'            => [Token::TYPE_UNRESERVED_KEYWORD,     false],
        'verbose'            => [Token::TYPE_TYPE_FUNC_NAME_KEYWORD, true],
        'version'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'view'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'views'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'volatile'           => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'when'               => [Token::TYPE_RESERVED_KEYWORD,       true],
        'where'              => [Token::TYPE_RESERVED_KEYWORD,       false],
        'whitespace'         => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'window'             => [Token::TYPE_RESERVED_KEYWORD,       false],
        'with'               => [Token::TYPE_RESERVED_KEYWORD,       false],
        'within'             => [Token::TYPE_UNRESERVED_KEYWORD,     false],
        'without'            => [Token::TYPE_UNRESERVED_KEYWORD,     false],
        'work'               => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'wrapper'            => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'write'              => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'xml'                => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'xmlattributes'      => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'xmlconcat'          => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'xmlelement'         => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'xmlexists'          => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'xmlforest'          => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'xmlnamespaces'      => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'xmlparse'           => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'xmlpi'              => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'xmlroot'            => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'xmlserialize'       => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'xmltable'           => [Token::TYPE_COL_NAME_KEYWORD,       true],
        'year'               => [Token::TYPE_UNRESERVED_KEYWORD,     false],
        'yes'                => [Token::TYPE_UNRESERVED_KEYWORD,     true],
        'zone'               => [Token::TYPE_UNRESERVED_KEYWORD,     true]
    ];

    /**
     * Checks whether a given string is a recognized keyword
     *
     * @param string $string
     * @return bool
     */
    public static function isKeyword(string $string): bool
    {
        return array_key_exists($string, self::LIST);
    }

    /**
     * Checks whether a given string is a keyword that can be used as a column label without AS
     *
     * @param string $string
     * @return bool
     */
    public static function isBareLabelKeyword(string $string): bool
    {
        return array_key_exists($string, self::LIST)
            && self::LIST[$string][1];
    }
}
