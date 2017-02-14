<?php
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . DIRECTORY_SEPARATOR . 'libpy2php');
require_once('libpy2php.php');
$__author__ = 'Andy Chu';
$__all__ = ['Error', 'CompilationError', 'EvaluationError', 'BadFormatter', 'BadPredicate', 'MissingFormatter', 'ConfigurationError', 'TemplateSyntaxError', 'UndefinedVariable', 'FromString', 'FromFile', 'Template', 'expand', 'Trace', 'FunctionRegistry', 'MakeTemplateGroup', 'SIMPLE_FUNC', 'ENHANCED_FUNC'];
require_once( 'StringIO.php');
require_once( 'pprint.php');
require_once( 're.php');
require_once( 'sys.php');
require_once( 'cgi.php');
require_once( 'time.php');
require_once( 'urllib.php');
require_once( 'urlparse.php');
/**
 * Base class for all exceptions in this module.
 * 
 * Thus you can "except jsontemplate.Error: to catch all exceptions thrown by
 * this module.
 */
class Error extends Exception {
    /**
     * This helps people debug their templates.
     * 
     * If a variable isn't defined, then some context is shown in the traceback.
     * TODO: Attach context for other errors.
     */
    function __str__() {
        if (method_exists($this, 'near')) {
            return sprintf('%s

Near: %s', [$this->args[0], pprint::pformat($this->near)]);
        }
        else {
            return $this->args[0];
        }
    }
}
/**
 * Errors in using the API, not a result of compilation or evaluation.
 */
class UsageError extends Error {
}
/**
 * Base class for errors that happen during the compilation stage.
 */
class CompilationError extends Error {
}
/**
 * Base class for errors that happen when expanding the template.
 * 
 * This class of errors generally involve the data dictionary or the execution of
 * the formatters.
 */
class EvaluationError extends Error {
    function __construct($msg,$original_exc_info=null) {
        Error::__construct($msg);
        $this->original_exc_info = $original_exc_info;
    }
}
/**
 * A bad formatter was specified, e.g. {variable|BAD}
 */
class BadFormatter extends CompilationError {
}
/**
 * A bad predicate was specified, e.g. {.BAD?}
 */
class BadPredicate extends CompilationError {
}
/**
 * Raised when formatters are required, and a variable is missing a formatter.
 */
class MissingFormatter extends CompilationError {
}
/**
 * Raised when the Template options are invalid and it can't even be compiled.
 */
class ConfigurationError extends CompilationError {
}
/**
 * Syntax error in the template text.
 */
class TemplateSyntaxError extends CompilationError {
}
/**
 * The template contains a variable name not defined by the data dictionary.
 */
class UndefinedVariable extends EvaluationError {
}
$_SECTION_RE = re::compile('(repeated)?\s*section\s+(.+)');
list($SIMPLE_FUNC, $ENHANCED_FUNC) = [0, 1];
$TEMPLATE_FORMATTER = 2;
/**
 * Abstract class for looking up formatters or predicates at compile time.
 * 
 * Users should implement either Lookup or LookupWithType, and then the
 * implementation calls LookupWithType.
 */
class FunctionRegistry extends object {
    /**
     * Lookup a function.
     * 
     * Args:
     * user_str: A raw string from the user, which may include uninterpreted
     * arguments.  For example, 'pluralize person people' or 'test? admin'
     * 
     * Returns:
     * A 2-tuple of (function, args)
     * function: Callable that formats data as a string
     * args: Extra arguments to be passed to the function at expansion time
     * Should be None to pass NO arguments, since it can pass a 0-tuple too.
     */
    function Lookup($user_str) {
        throw new NotImplementedError;
    }
    function LookupWithType($user_str) {
        list($func, $args) = $this->Lookup($user_str);
        return [$func, $args, $ENHANCED_FUNC];
    }
}
/**
 * By default, formatters/predicates which start with a non-lowercase letter take
 * contexts rather than just the cursor.
 */
function _DecideFuncType($user_str) {
    if ($user_str[0]->islower()) {
        return $SIMPLE_FUNC;
    }
    else {
        return $ENHANCED_FUNC;
    }
}
/**
 * Look up functions in a simple dictionary.
 */
class DictRegistry extends FunctionRegistry {
    function __construct($func_dict) {
        $this->func_dict = $func_dict;
    }
    function LookupWithType($user_str) {
        return [$this->func_dict->get($user_str), null, _DecideFuncType($user_str)];
    }
}
/**
 * Look up functions in a (higher-order) function.
 */
class CallableRegistry extends FunctionRegistry {
    function __construct($func) {
        $this->func = $func;
    }
    function LookupWithType($user_str) {
        return [$this->func($user_str), null, _DecideFuncType($user_str)];
    }
}
/**
 * Lookup functions with arguments.
 * 
 * The function name is identified by a prefix.  The character after the prefix,
 * usually a space, is considered the argument delimiter (similar to sed/perl's
 * s/foo/bar s|foo|bar syntax).
 */
class PrefixRegistry extends FunctionRegistry {
    /**
     * Args:
     * functions: List of 2-tuples (prefix, function), e.g.
     * [('pluralize', _Pluralize), ('cycle', _Cycle)]
     */
    function __construct($functions) {
        $this->functions = $functions;
    }
    function Lookup($user_str) {
        foreach( $this->functions as list($prefix, $func) ) {
            if ($user_str->startswith($prefix)) {
                $i = count($prefix);
                try {
                    $splitchar = $user_str[$i];
                }
                catch(IndexError $e) {
                                        $args = [];
                }
//                py2php: else block not supported in PHP.
//                else {
//                    $args = array_slice($user_str->split($splitchar), 1, null);
//                }
                return [$func, $args];
            }
        }
        return [null, []];
    }
}
/**
 * A reference from one template to another.
 * 
 * The _TemplateRef sits statically in the program tree as one of the formatters.
 * At runtime, _DoSubstitute calls Resolve() with the group being used.
 */
class _TemplateRef extends object {
    function __construct($name=null,$template=null) {
        $this->name = $name;
        $this->template = $template;
    }
    function Resolve($context) {
        if ($this->template) {
            return $this->template;
        }
        if ($context->group) {
            return $context->group->get($this->name);
        }
        else {
            throw new new EvaluationError(sprintf('Couldn\'t find template with name %r (create a template group?)', $this->name));
        }
    }
}
/**
 * Each template owns a _TemplateRegistry.
 * 
 * LookupWithType always returns a TemplateRef to the template compiler
 * (_ProgramBuilder).  At runtime, which may be after MakeTemplateGroup is
 * called, the names can be resolved.
 */
class _TemplateRegistry extends FunctionRegistry {
    /**
     * Args:
     * owner: The Template instance that owns this formatter.  (There should be
     * exactly one)
     */
    function __construct($owner) {
        $this->owner = $owner;
    }
    /**
     * Returns:
     * ref: Either a template instance (itself) or _TemplateRef
     */
    function LookupWithType($user_str) {
        $prefix = 'template ';
        $ref = null;
        if ($user_str->startswith($prefix)) {
            $name = array_slice($user_str, count($prefix), null);
            if (($name == 'SELF')) {
                $ref = py2php_kwargs_function_call('new _TemplateRef', [], ["template" => $this->owner]);
            }
            else {
                $ref = new _TemplateRef($name);
            }
        }
        return [$ref, [], $TEMPLATE_FORMATTER];
    }
}
/**
 * Look up functions in chain of other FunctionRegistry instances.
 */
class ChainedRegistry extends FunctionRegistry {
    function __construct($registries) {
        $this->registries = $registries;
    }
    function LookupWithType($user_str) {
        foreach( $this->registries as $registry ) {
            list($func, $args, $func_type) = $registry->LookupWithType($user_str);
            if ($func) {
                return [$func, $args, $func_type];
            }
        }
        return [null, null, $SIMPLE_FUNC];
    }
}
/**
 * Receives method calls from the parser, and constructs a tree of _Section()
 * instances.
 */
class _ProgramBuilder extends object {
    /**
     * Args:
     * formatters: See docstring for _CompileTemplate
     * predicates: See docstring for _CompileTemplate
     */
    function __construct($formatters,$predicates,$template_registry) {
        $this->current_section = new _Section(null);
        $this->stack = [$this->current_section];
        if (isinstance($formatters, $dict)) {
            $formatters = new DictRegistry($formatters);
        }
        else if (is_function($formatters)) {
            $formatters = new CallableRegistry($formatters);
        }
        $default_formatters = new PrefixRegistry([['pluralize', $_Pluralize], ['cycle', $_Cycle], ['strftime-local', $_StrftimeLocal], ['strftime-gm', $_StrftimeGm], ['strftime', $_StrftimeLocal]]);
        $this->formatters = new ChainedRegistry([$formatters, $template_registry, new DictRegistry($_DEFAULT_FORMATTERS), $default_formatters]);
        if (isinstance($predicates, $dict)) {
            $predicates = new DictRegistry($predicates);
        }
        else if (is_function($predicates)) {
            $predicates = new CallableRegistry($predicates);
        }
        $default_predicates = new PrefixRegistry([['test', $_TestAttribute], ['template', $_TemplateExists]]);
        $this->predicates = new ChainedRegistry([$predicates, new DictRegistry($_DEFAULT_PREDICATES), $default_predicates]);
    }
    /**
     * Args:
     * statement: Append a literal
     */
    function Append($statement) {
        $this->current_section->Append($statement);
    }
    /**
     * The user's formatters are consulted first, then the default formatters.
     */
    function _GetFormatter($format_str) {
        list($formatter, $args, $func_type) = $this->formatters->LookupWithType($format_str);
        if ($formatter) {
            return [$formatter, $args, $func_type];
        }
        else {
            throw new new BadFormatter(sprintf('%r is not a valid formatter', $format_str));
        }
    }
    /**
     * The user's predicates are consulted first, then the default predicates.
     */
    function _GetPredicate($pred_str,$test_attr=false) {
        list($predicate, $args, $func_type) = $this->predicates->LookupWithType($pred_str);
        if ($predicate) {
            $pred = [$predicate, $args, $func_type];
        }
        else {
            if ($test_attr) {
                assert($pred_str->endswith('?'));
                $pred = [$_TestAttribute, [array_slice($pred_str, null, -1)], $ENHANCED_FUNC];
            }
            else {
                throw new new BadPredicate(sprintf('%r is not a valid predicate', $pred_str));
            }
        }
        return $pred;
    }
    function AppendSubstitution($name,$formatters) {
        $formatters = /* py2php.fixme listcomp unsupported. */;
        $this->current_section->Append([$_DoSubstitute, [$name, $formatters]]);
    }
    function AppendTemplateSubstitution($name) {
        $formatters = [$this->_GetFormatter('template ' . $name)];
        $this->current_section->Append([$_DoSubstitute, [null, $formatters]]);
    }
    function _NewSection($func,$new_block) {
        $this->current_section->Append([$func, $new_block]);
        $this->stack[] = $new_block;
        $this->current_section = $new_block;
    }
    /**
     * For sections or repeated sections.
     */
    function NewSection($token_type,$section_name,$pre_formatters) {
        $pre_formatters = /* py2php.fixme listcomp unsupported. */;
        if (($token_type == $REPEATED_SECTION_TOKEN)) {
            $new_block = new _RepeatedSection($section_name, $pre_formatters);
            $func = $_DoRepeatedSection;
        }
        else if (($token_type == $SECTION_TOKEN)) {
            $new_block = new _Section($section_name, $pre_formatters);
            $func = $_DoSection;
        }
        else if (($token_type == $DEF_TOKEN)) {
            $new_block = new _Section($section_name, []);
            $func = $_DoDef;
        }
        else {
            throw new $AssertionError(sprintf('Invalid token type %s', $token_type));
        }
        $this->_NewSection($func, $new_block);
    }
    /**
     * {.or ...} Can appear inside predicate blocks or section blocks, with
     * slightly different meaning.
     */
    function NewOrClause($pred_str) {
        if ($pred_str) {
            $pred = py2php_kwargs_method_call($this, '_GetPredicate', [$pred_str], ["test_attr" => false]);
        }
        else {
            $pred = null;
        }
        $this->current_section->NewOrClause($pred);
    }
    function AlternatesWith() {
        $this->current_section->AlternatesWith();
    }
    /**
     * For chains of predicate clauses.
     */
    function NewPredicateSection($pred_str,$test_attr=false) {
        $pred = py2php_kwargs_method_call($this, '_GetPredicate', [$pred_str], ["test_attr" => $test_attr]);
        $block = new _PredicateSection();
        $block->NewOrClause($pred);
        $this->_NewSection($_DoPredicates, $block);
    }
    function EndSection() {
        $this->stack->pop();
        $this->current_section = $this->stack[-1];
    }
    function Root() {
        return $this->current_section;
    }
}
class _AbstractSection extends object {
    function __construct() {
        $this->current_clause = [];
    }
    /**
     * Append a statement to this block.
     */
    function Append($statement) {
        $this->current_clause[] = $statement;
    }
    function AlternatesWith() {
        throw new new TemplateSyntaxError('{.alternates with} can only appear with in {.repeated section ...}');
    }
    function NewOrClause($pred_str) {
        throw new NotImplementedError;
    }
}
/**
 * Represents a (repeated) section.
 */
class _Section extends _AbstractSection {
    /**
     * Args:
     * section_name: name given as an argument to the section, None for the root
     * section
     * pre_formatters: List of formatters to be applied to the data dictinoary
     * before the expansion
     */
    function __construct($section_name,$pre_formatters=[]) {
        _AbstractSection::__construct();
        $this->section_name = $section_name;
        $this->pre_formatters = $pre_formatters;
        $this->statements = ['default' => $this->current_clause];
    }
    function __repr__() {
        return sprintf('<Section %s>', $this->section_name);
    }
    function Statements($clause='default') {
        return $this->statements->get($clause, []);
    }
    function NewOrClause($pred) {
        if ($pred) {
            throw new new TemplateSyntaxError('{.or} clause only takes a predicate inside predicate blocks');
        }
        $this->current_clause = [];
        $this->statements['or'] = $this->current_clause;
    }
}
/**
 * Repeated section is like section, but it supports {.alternates with}
 */
class _RepeatedSection extends _Section {
    function AlternatesWith() {
        $this->current_clause = [];
        $this->statements['alternates with'] = $this->current_clause;
    }
}
/**
 * Represents a sequence of predicate clauses.
 */
class _PredicateSection extends _AbstractSection {
    function __construct() {
        _AbstractSection::__construct();
        $this->clauses = [];
    }
    function NewOrClause($pred) {
        $pred = $pred || [function ($x) {return true;}, null, $SIMPLE_FUNC];
        $this->current_clause = [];
        $this->clauses[] = [$pred, $this->current_clause];
    }
}
/**
 * A stack frame.
 */
class _Frame extends object {
    function __construct($context,$index=-1) {
        $this->context = $context;
        $this->index = $index;
    }
    function __str__() {
        return sprintf('Frame %s (%s)', [$this->context, $this->index]);
    }
}
/**
 * Allows scoped lookup of variables.
 * 
 * If the variable isn't in the current context, then we search up the stack.
 * This object also stores the group.
 */
class _ScopedContext extends object {
    /**
     * Args:
     * context: The root context
     * undefined_str: See Template() constructor.
     * group: Used by the {.if template FOO} predicate, and _DoSubstitute
     * which is passed the context.
     */
    function __construct($context,$undefined_str,$group=null) {
        $this->stack = [new _Frame($context)];
        $this->undefined_str = $undefined_str;
        $this->group = $group;
        $this->root = $context;
    }
    /**
     * For {.template FOO} substitution.
     */
    function Root() {
        return $this->root;
    }
    function HasTemplate($name) {
        if (!($this->group)) {
            return false;
        }
        return in_array($name, $this->group);
    }
    /**
     * Given a section name, push it on the top of the stack.
     * 
     * Returns:
     * The new section, or None if there is no such section.
     */
    function PushSection($name,$pre_formatters) {
        if (($name == '@')) {
            $value = $this->stack[-1]->context;
        }
        else {
            $top = $this->stack[-1]->context;
            try {
                $value = $top->get($name);
            }
            catch(AttributeError $e) {
                                throw new new EvaluationError(sprintf('Can\'t get name %r from top value %s', [$name, $top]));
            }
        }
        foreach( enumerate($pre_formatters) as list($i, list($f, $args, $formatter_type)) ) {
            if (($formatter_type == $ENHANCED_FUNC)) {
                $value = $f($value, $this, $args);
            }
            else if (($formatter_type == $SIMPLE_FUNC)) {
                $value = $f($value);
            }
            else {
                assert(false, sprintf('Invalid formatter type %r', $formatter_type));
            }
        }
        $this->stack[] = new _Frame($value);
        return $value;
    }
    function Pop() {
        $this->stack->pop();
    }
    /**
     * Advance to the next item in a repeated section.
     * 
     * Raises:
     * StopIteration if there are no more elements
     */
    function Next() {
        $stacktop = $this->stack[-1];
        if (($stacktop->index == -1)) {
            $stacktop = py2php_kwargs_function_call('new _Frame', [null], ["index" => 0]);
            $this->stack[] = $stacktop;
        }
        $context_array = $this->stack[-2]->context;
        if (($stacktop->index == count($context_array))) {
            $this->stack->pop();
            throw new StopIteration;
        }
        $stacktop->context = $context_array[$stacktop->index];
        $stacktop->index += 1;
        return true;
    }
    function _Undefined($name) {
        if (($this->undefined_str == null)) {
            throw new new UndefinedVariable(sprintf('%r is not defined', $name));
        }
        else {
            return $this->undefined_str;
        }
    }
    /**
     * Look up the stack for the given name.
     */
    function _LookUpStack($name) {
        $i = (count($this->stack) - 1);
        while (1) {
            $frame = $this->stack[$i];
            if (($name == '@index')) {
                if (($frame->index != -1)) {
                    return $frame->index;
                }
            }
            else {
                $context = $frame->context;
                if (method_exists($context, 'get')) {
                    try {
                        return $context[$name];
                    }
                    catch(KeyError $e) {
                                            }
                }
            }
            $i -= 1;
            if (($i <= -1)) {
                return $this->_Undefined($name);
            }
        }
    }
    /**
     * Get the value associated with a name in the current context.
     * 
     * The current context could be an dictionary in a list, or a dictionary
     * outside a list.
     * 
     * Args:
     * name: name to lookup, e.g. 'foo' or 'foo.bar.baz'
     * 
     * Returns:
     * The value, or self.undefined_str
     * 
     * Raises:
     * UndefinedVariable if self.undefined_str is not set
     */
    function Lookup($name) {
        if (($name == '@')) {
            return $this->stack[-1]->context;
        }
        $parts = $name->split('.');
        $value = $this->_LookUpStack($parts[0]);
        foreach( array_slice($parts, 1, null) as $part ) {
            try {
                $value = $value[$part];
            }
            catch(Exception $e) {
                                return $this->_Undefined($part);
            }
        }
        return $value;
    }
}
/**
 * The default default formatter!.
 */
function _ToString($x) {
    if (($x == null)) {
        return 'null';
    }
    if (isinstance($x, $basestring)) {
        return $x;
    }
    return pprint::pformat($x);
}
function _Html($x) {
    if (!(isinstance($x, $basestring))) {
        $x = pyjslib_str($x);
    }
    return cgi::escape($x);
}
function _HtmlAttrValue($x) {
    if (!(isinstance($x, $basestring))) {
        $x = pyjslib_str($x);
    }
    return py2php_kwargs_function_call('cgi::escape', [$x], ["quote" => true]);
}
/**
 * Returns an absolute URL, given the current node as a relative URL.
 * 
 * Assumes that the context has a value named 'base-url'.  This is a little like
 * the HTML <base> tag, but implemented with HTML generation.
 * 
 * Raises:
 * UndefinedVariable if 'base-url' doesn't exist
 */
function _AbsUrl($relative_url,$context,$unused_args) {
    return urlparse::urljoin($context->Lookup('base-url'), $relative_url);
}
/**
 * We use this on lists as section pre-formatters; it probably works for
 * strings too.
 */
function _Reverse($x) {
    return list(reversed($x));
}
/**
 * dictionary -> list of pairs
 */
function _Pairs($data) {
    $keys = sorted($data);
    return /* py2php.fixme listcomp unsupported. */;
}
$_DEFAULT_FORMATTERS = ['html' => $_Html, 'html-attr-value' => $_HtmlAttrValue, 'htmltag' => $_HtmlAttrValue, 'raw' => function ($x) {return $x;}, 'size' => function ($value) {return pyjslib_str(count($value));}, 'url-params' => function ($x) {return py2php_kwargs_function_call('urllib::urlencode', [$x], ["doseq" => true]);}, 'url-param-value' => urllib::quote_plus, 'str' => $_ToString, 'repr' => $repr, 'upper' => function ($x) {return $x->upper();}, 'lower' => function ($x) {return $x->lower();}, 'plain-url' => function ($x) {return sprintf('<a href="%s">%s</a>', [py2php_kwargs_function_call('cgi::escape', [$x], ["quote" => true]), cgi::escape($x)]);}, 'AbsUrl' => $_AbsUrl, 'json' => null, 'js-string' => null, 'reverse' => $_Reverse, 'pairs' => $_Pairs];
/**
 * Formatter to pluralize words.
 */
function _Pluralize($value,$unused_context,$args) {
    if ((count($args) == 0)) {
        list($s, $p) = ['', 's'];
    }
    else if ((count($args) == 1)) {
        list($s, $p) = ['', $args[0]];
    }
    else if ((count($args) == 2)) {
        list($s, $p) = $args;
    }
    else {
        throw new AssertionError;
    }
    if (($value > 1)) {
        return $p;
    }
    else {
        return $s;
    }
}
/**
 * Cycle between various values on consecutive integers.
 */
function _Cycle($value,$unused_context,$args) {
    return $args[(($value - 1) % count($args))];
}
function _StrftimeHelper($args,$time_tuple) {
    try {
        $format_str = $args[0];
    }
    catch(IndexError $e) {
                return time::asctime($time_tuple);
    }
//    py2php: else block not supported in PHP.
//    else {
//        return time::strftime($format_str, $time_tuple);
//    }
}
/**
 * Convert a timestamp in seconds to a string based on the format string.
 * 
 * Returns GM time.
 */
function _StrftimeGm($value,$unused_context,$args) {
    $time_tuple = time::gmtime($value);
    return _StrftimeHelper($args, $time_tuple);
}
/**
 * Convert a timestamp in seconds to a string based on the format string.
 * 
 * Returns local time.
 */
function _StrftimeLocal($value,$unused_context,$args) {
    $time_tuple = time::localtime($value);
    return _StrftimeHelper($args, $time_tuple);
}
function _IsDebugMode($unused_value,$context,$unused_args) {
    return _TestAttribute($unused_value, $context, ['debug']);
}
/**
 * Cycle between various values on consecutive integers.
 */
function _TestAttribute($unused_value,$context,$args) {
    try {
        $name = $args[0];
    }
    catch(IndexError $e) {
                throw new new EvaluationError('The "test" predicate requires an argument.');
    }
    try {
        return bool($context->Lookup($name));
    }
    catch(UndefinedVariable $e) {
                return false;
    }
}
/**
 * Returns whether the given name is in the current Template's template group.
 */
function _TemplateExists($unused_value,$context,$args) {
    try {
        $name = $args[0];
    }
    catch(IndexError $e) {
                throw new new EvaluationError('The "template" predicate requires an argument.');
    }
    return $context->HasTemplate($name);
}
$_SINGULAR = function ($x) {return ($x == 1);};
$_PLURAL = function ($x) {return ($x > 1);};
$_DEFAULT_PREDICATES = ['singular?' => $_SINGULAR, 'plural?' => $_PLURAL, 'Debug?' => $_IsDebugMode, 'singular' => $_SINGULAR, 'plural' => $_PLURAL];
/**
 * Split and validate metacharacters.
 * 
 * Example: '{}' -> ('{', '}')
 * 
 * This is public so the syntax highlighter and other tools can use it.
 */
function SplitMeta($meta) {
    $n = count($meta);
    if ((($n % 2) == 1)) {
        throw new new ConfigurationError(sprintf('%r has an odd number of metacharacters', $meta));
    }
    return [array_slice($meta, null, ($n / 2)), array_slice($meta, ($n / 2), null)];
}
$_token_re_cache = [];
/**
 * Return a (compiled) regular expression for tokenization.
 * 
 * Args:
 * meta_left, meta_right: e.g. '{' and '}'
 * 
 * - The regular expressions are memoized.
 * - This function is public so the syntax highlighter can use it.
 */
function MakeTokenRegex($meta_left,$meta_right) {
    $key = [$meta_left, $meta_right];
    if (!in_array($key, $_token_re_cache)) {
        $_token_re_cache[$key] = re::compile('(' . re::escape($meta_left) . '\S.*?' . re::escape($meta_right) . ')');
    }
    return $_token_re_cache[$key];
}
list($LITERAL_TOKEN, $META_LITERAL_TOKEN, $SUBST_TOKEN, $SECTION_TOKEN, $REPEATED_SECTION_TOKEN, $PREDICATE_TOKEN, $IF_TOKEN, $ALTERNATES_TOKEN, $OR_TOKEN, $END_TOKEN, $SUBST_TEMPLATE_TOKEN, $DEF_TOKEN, $COMMENT_BEGIN_TOKEN, $COMMENT_END_TOKEN) = pyjslib_range(14);
$COMMENT_BEGIN = '##BEGIN';
$COMMENT_END = '##END';
$OPTION_STRIP_LINE = '.OPTION strip-line';
$OPTION_END = '.END';
/**
 * Helper function for matching certain directives.
 */
function _MatchDirective($token) {
    if ($token->startswith('.')) {
        $token = array_slice($token, 1, null);
    }
    else {
        return [null, null];
    }
    if (($token == 'end')) {
        return [$END_TOKEN, null];
    }
    if (($token == 'alternates with')) {
        return [$ALTERNATES_TOKEN, $token];
    }
    if ($token->startswith('or')) {
        if (($token->strip() == 'or')) {
            return [$OR_TOKEN, null];
        }
        else {
            $pred_str = array_slice($token, 2, null)->strip();
            return [$OR_TOKEN, $pred_str];
        }
    }
    $match = _SECTION_RE::match($token);
    if ($match) {
        list($repeated, $section_name) = $match->groups();
        if ($repeated) {
            return [$REPEATED_SECTION_TOKEN, $section_name];
        }
        else {
            return [$SECTION_TOKEN, $section_name];
        }
    }
    if ($token->startswith('template ')) {
        return [$SUBST_TEMPLATE_TOKEN, array_slice($token, 9, null)->strip()];
    }
    if ($token->startswith('define ')) {
        return [$DEF_TOKEN, array_slice($token, 7, null)->strip()];
    }
    if ($token->startswith('if ')) {
        return [$IF_TOKEN, array_slice($token, 3, null)->strip()];
    }
    if ($token->endswith('?')) {
        return [$PREDICATE_TOKEN, $token];
    }
    return [null, null];
}
/**
 * Yields tokens, which are 2-tuples (TOKEN_TYPE, token_string).
 */
function _Tokenize($template_str,$meta_left,$meta_right,$whitespace) {
    $trimlen = count($meta_left);
    $token_re = MakeTokenRegex($meta_left, $meta_right);
    $do_strip = ($whitespace == 'strip-line');
    $do_strip_part = false;
    foreach( $template_str->splitlines(true) as $line ) {
        if ($do_strip || $do_strip_part) {
            $line = $line->strip();
        }
        $tokens = $token_re->split($line);
        if ((count($tokens) == 3)) {
            if ($tokens[0]->isspace() || !($tokens[0]) && $tokens[2]->isspace() || !($tokens[2])) {
                $token = array_slice($tokens[1], $trimlen, -$trimlen - $trimlen);
                if (($token == $COMMENT_BEGIN)) {
                    yield([$COMMENT_BEGIN_TOKEN, null]);
                    continue;
                }
                if (($token == $COMMENT_END)) {
                    yield([$COMMENT_END_TOKEN, null]);
                    continue;
                }
                if (($token == $OPTION_STRIP_LINE)) {
                    $do_strip_part = true;
                    continue;
                }
                if (($token == $OPTION_END)) {
                    $do_strip_part = false;
                    continue;
                }
                if ($token->startswith('#')) {
                    continue;
                }
                list($token_type, $token) = _MatchDirective($token);
                if (($token_type != null)) {
                    yield([$token_type, $token]);
                    continue;
                }
            }
        }
        foreach( enumerate($tokens) as list($i, $token) ) {
            if ((($i % 2) == 0)) {
                yield([$LITERAL_TOKEN, $token]);
            }
            else {
                assert($token->startswith($meta_left), pyjslib_repr($token));
                assert($token->endswith($meta_right), pyjslib_repr($token));
                $token = array_slice($token, $trimlen, -$trimlen - $trimlen);
                if (($token == $COMMENT_BEGIN)) {
                    yield([$COMMENT_BEGIN_TOKEN, null]);
                    continue;
                }
                if (($token == $COMMENT_END)) {
                    yield([$COMMENT_END_TOKEN, null]);
                    continue;
                }
                if (($token == $OPTION_STRIP_LINE)) {
                    $do_strip_part = true;
                    continue;
                }
                if (($token == $OPTION_END)) {
                    $do_strip_part = false;
                    continue;
                }
                if ($token->startswith('#')) {
                    continue;
                }
                if ($token->startswith('.')) {
                    $literal = ['.meta-left' => $meta_left, '.meta-right' => $meta_right, '.space' => ' ', '.tab' => '	', '.newline' => '
']->get($token);
                    if (($literal != null)) {
                        yield([$META_LITERAL_TOKEN, $literal]);
                        continue;
                    }
                    list($token_type, $token) = _MatchDirective($token);
                    if (($token_type != null)) {
                        yield([$token_type, $token]);
                    }
                }
                else {
                    yield([$SUBST_TOKEN, $token]);
                }
            }
        }
    }
}
/**
 * Compile the template string, calling methods on the 'program builder'.
 * 
 * Args:
 * template_str: The template string.  It should not have any compilation
 * options in the header -- those are parsed by FromString/FromFile
 * 
 * builder: The interface of _ProgramBuilder isn't fixed.  Use at your own
 * risk.
 * 
 * meta: The metacharacters to use, e.g. '{}', '[]'.
 * 
 * default_formatter: The formatter to use for substitutions that are missing a
 * formatter.  The 'str' formatter the "default default" -- it just tries
 * to convert the context value to a string in some unspecified manner.
 * 
 * whitespace: 'smart' or 'strip-line'.  In smart mode, if a directive is alone
 * on a line, with only whitespace on either side, then the whitespace is
 * removed.  In 'strip-line' mode, every line is stripped of its
 * leading and trailing whitespace.
 * 
 * Returns:
 * The compiled program (obtained from the builder)
 * 
 * Raises:
 * The various subclasses of CompilationError.  For example, if
 * default_formatter=None, and a variable is missing a formatter, then
 * MissingFormatter is raised.
 * 
 * This function is public so it can be used by other tools, e.g. a syntax
 * checking tool run before submitting a template to source control.
 */
function _CompileTemplate($template_str,$builder,$meta='{}',$format_char='|',$default_formatter='str',$whitespace='smart') {
    list($meta_left, $meta_right) = SplitMeta($meta);
    if (!in_array($format_char, [':', '|'])) {
        throw new new ConfigurationError(sprintf('Only format characters : and | are accepted (got %r)', $format_char));
    }
    if (!in_array($whitespace, ['smart', 'strip-line'])) {
        throw new new ConfigurationError(sprintf('Invalid whitespace mode %r', $whitespace));
    }
    $balance_counter = 0;
    $comment_counter = 0;
    $has_defines = false;
    foreach( _Tokenize($template_str, $meta_left, $meta_right, $whitespace) as list($token_type, $token) ) {
        if (($token_type == $COMMENT_BEGIN_TOKEN)) {
            $comment_counter += 1;
            continue;
        }
        if (($token_type == $COMMENT_END_TOKEN)) {
            $comment_counter -= 1;
            if (($comment_counter < 0)) {
                throw new new CompilationError('Got too many ##END markers');
            }
            continue;
        }
        if (($comment_counter > 0)) {
            continue;
        }
        if (in_array($token_type, [$LITERAL_TOKEN, $META_LITERAL_TOKEN])) {
            if ($token) {
                $builder->Append($token);
            }
            continue;
        }
        if (in_array($token_type, [$SECTION_TOKEN, $REPEATED_SECTION_TOKEN, $DEF_TOKEN])) {
            $parts = /* py2php.fixme listcomp unsupported. */;
            if ((count($parts) == 1)) {
                $name = $parts[0];
                $formatters = [];
            }
            else {
                $name = $parts[0];
                $formatters = array_slice($parts, 1, null);
            }
            $builder->NewSection($token_type, $name, $formatters);
            $balance_counter += 1;
            if (($token_type == $DEF_TOKEN)) {
                $has_defines = true;
            }
            continue;
        }
        if (($token_type == $PREDICATE_TOKEN)) {
            py2php_kwargs_method_call($builder, 'NewPredicateSection', [$token], ["test_attr" => true]);
            $balance_counter += 1;
            continue;
        }
        if (($token_type == $IF_TOKEN)) {
            py2php_kwargs_method_call($builder, 'NewPredicateSection', [$token], ["test_attr" => false]);
            $balance_counter += 1;
            continue;
        }
        if (($token_type == $OR_TOKEN)) {
            $builder->NewOrClause($token);
            continue;
        }
        if (($token_type == $ALTERNATES_TOKEN)) {
            $builder->AlternatesWith();
            continue;
        }
        if (($token_type == $END_TOKEN)) {
            $balance_counter -= 1;
            if (($balance_counter < 0)) {
                throw new new TemplateSyntaxError(sprintf('Got too many %send%s statements.  You may have mistyped an earlier \'section\' or \'repeated section\' directive.', [$meta_left, $meta_right]));
            }
            $builder->EndSection();
            continue;
        }
        if (($token_type == $SUBST_TOKEN)) {
            $parts = /* py2php.fixme listcomp unsupported. */;
            if ((count($parts) == 1)) {
                if (($default_formatter == null)) {
                    throw new new MissingFormatter('This template requires explicit formatters.');
                }
                $name = $token;
                $formatters = [$default_formatter];
            }
            else {
                $name = $parts[0];
                $formatters = array_slice($parts, 1, null);
            }
            $builder->AppendSubstitution($name, $formatters);
            continue;
        }
        if (($token_type == $SUBST_TEMPLATE_TOKEN)) {
            $builder->AppendTemplateSubstitution($token);
            continue;
        }
    }
    if (($balance_counter != 0)) {
        throw new new TemplateSyntaxError(sprintf('Got too few %send%s statements', [$meta_left, $meta_right]));
    }
    if (($comment_counter != 0)) {
        throw new new CompilationError(sprintf('Got %d more {##BEGIN}s than {##END}s', $comment_counter));
    }
    return [$builder->Root(), $has_defines];
}
$_OPTION_RE = re::compile('^([a-zA-Z\-]+):\s*(.*)');
$_OPTION_NAMES = ['meta', 'format-char', 'default-formatter', 'undefined-str', 'whitespace'];
/**
 * Like FromFile, but takes a string.
 */
function FromString($s,$kwargs) {
    $f = StringIO::StringIO($s);
    return py2php_kwargs_function_call('FromFile', [$f], $kwargs);
}
/**
 * Parse a template from a file, using a simple file format.
 * 
 * This is useful when you want to include template options in a data file,
 * rather than in the source code.
 * 
 * The format is similar to HTTP or E-mail headers.  The first lines of the file
 * can specify template options, such as the metacharacters to use.  One blank
 * line must separate the options from the template body.
 * 
 * Example:
 * 
 * default-formatter: none
 * meta: {{}}
 * format-char: :
 * <blank line required>
 * Template goes here: {{variable:html}}
 * 
 * Args:
 * f: A file handle to read from.  Caller is responsible for opening and
 * closing it.
 */
function FromFile($f,$more_formatters=function ($x) {return null;},$more_predicates=function ($x) {return null;},$_constructor=null) {
    $_constructor = $_constructor || Template;
    $options = [];
    while (1) {
        $line = $f->readline();
        $match = _OPTION_RE::match($line);
        if ($match) {
            list($name, $value) = [$match->group(1), $match->group(2)];
            $name = $name->lower();
            $name = $name->encode('utf-8');
            if (in_array($name, $_OPTION_NAMES)) {
                $name = $name->replace('-', '_');
                $value = $value->strip();
                if (($name == 'default_formatter') && ($value->lower() == 'none')) {
                    $value = null;
                }
                $options[$name] = $value;
            }
            else {
                break;
            }
        }
        else {
            break;
        }
    }
    if ($options) {
        if ($line->strip()) {
            throw new new CompilationError(sprintf('Must be one blank line between template options and body (got %r)', $line));
        }
        $body = $f->read();
    }
    else {
        $body = ($line + $f->read());
    }
    return py2php_kwargs_function_call('$_constructor', [$body], $options);
}
/**
 * Represents a compiled template.
 * 
 * Like many template systems, the template string is compiled into a program,
 * and then it can be expanded any number of times.  For example, in a web app,
 * you can compile the templates once at server startup, and use the expand()
 * method at request handling time.  expand() uses the compiled representation.
 * 
 * There are various options for controlling parsing -- see _CompileTemplate.
 * Don't go crazy with metacharacters.  {}, [], {{}} or <> should cover nearly
 * any circumstance, e.g. generating HTML, CSS XML, JavaScript, C programs, text
 * files, etc.
 */
class Template extends object {
    /**
     * Args:
     * template_str: The template string.
     * 
     * more_formatters:
     * Something that can map format strings to formatter functions.  One of:
     * - A plain dictionary of names -> functions  e.g. {'html': cgi.escape}
     * - A higher-order function which takes format strings and returns
     * formatter functions.  Useful for when formatters have parsed
     * arguments.
     * - A FunctionRegistry instance, giving the most control.  This allows
     * formatters which takes contexts as well.
     * 
     * more_predicates:
     * Like more_formatters, but for predicates.
     * 
     * undefined_str: A string to appear in the output when a variable to be
     * substituted is missing.  If None, UndefinedVariable is raised.
     * (Note: This is not really a compilation option, because affects
     * template expansion rather than compilation.  Nonetheless we make it a
     * constructor argument rather than an .expand() argument for
     * simplicity.)
     * 
     * It also accepts all the compile options that _CompileTemplate does.
     */
    function __construct($template_str,$more_formatters,$more_predicates=function ($x) {return null;},$undefined_str=function ($x) {return null;},$compile_options=null) {
        $r = new _TemplateRegistry($this);
        $this->undefined_str = $undefined_str;
        $this->group = [];
        $builder = new _ProgramBuilder($more_formatters, $more_predicates, $r);
        if (($template_str != null)) {
            list($this->_program, $this->has_defines) = py2php_kwargs_function_call('_CompileTemplate', [$template_str,$builder], $compile_options);
            $this->group = _MakeGroupFromRootSection($this->_program, $this->undefined_str);
        }
    }
    static function _FromSection($section,$group,$undefined_str) {
        $t = py2php_kwargs_function_call('new Template', [null], ["undefined_str" => $undefined_str]);
        $t->_program = $section;
        $t->has_defines = false;
        $t->group = $group;
        return $t;
    }
    function _Statements() {
        return $this->_program->Statements();
    }
    /**
     * Allow this template to reference templates in the group.
     * 
     * Args:
     * group: dictionary of template name -> compiled Template instance
     */
    function _UpdateTemplateGroup($group) {
        $bad = [];
        foreach( $group as $name ) {
            if (in_array($name, $this->group)) {
                $bad[] = $name;
            }
        }
        if ($bad) {
            throw new new UsageError(sprintf('This template already has these named templates defined: %s', $bad));
        }
        $this->group->update($group);
    }
    /**
     * Check that the template names referenced in this template exist.
     */
    function _CheckRefs() {
    }
    /**
     * Low level method to expand the template piece by piece.
     * 
     * Args:
     * data_dict: The JSON data dictionary.
     * callback: A callback which should be called with each expanded token.
     * group: Dictionary of name -> Template instance (for styles)
     * 
     * Example: You can pass 'f.write' as the callback to write directly to a file
     * handle.
     */
    function execute($data_dict,$callback,$group=null,$trace=null) {
        $group = $group || $this->group;
        $context = py2php_kwargs_function_call('new _ScopedContext', [$data_dict,$this->undefined_str], ["group" => $group]);
        _Execute($this->_program->Statements(), $context, $callback, $trace);
    }
    public $render = $execute;
    /**
     * Expands the template with the given data dictionary, returning a string.
     * 
     * This is a small wrapper around execute(), and is the most convenient
     * interface.
     * 
     * Args:
     * data_dict: The JSON data dictionary.  Like the builtin dict() constructor,
     * it can take a single dictionary as a positional argument, or arbitrary
     * keyword arguments.
     * trace: Trace object for debugging
     * style: Template instance to be treated as a style for this template (the
     * "outside")
     * 
     * Returns:
     * The return value could be a str() or unicode() instance, depending on the
     * the type of the template string passed in, and what the types the strings
     * in the dictionary are.
     */
    function expand($args,...$kwargs) {
        if ($args) {
            if ((count($args) == 1)) {
                $data_dict = $args[0];
                $trace = $kwargs->get('trace');
                $style = $kwargs->get('style');
            }
            else {
                throw new $TypeError(sprintf('expand() only takes 1 positional argument (got %s)', $args));
            }
        }
        else {
            $data_dict = $kwargs;
            $trace = null;
            $style = null;
        }
        $tokens = [];
        if ($style) {
            py2php_kwargs_method_call($style, 'execute', [$data_dict,$tokens->append], ["group" => $this->group,"trace" => $trace]);
        }
        else {
            py2php_kwargs_method_call($this, 'execute', [$data_dict,$tokens->append], ["group" => $this->group,"trace" => $trace]);
        }
        return JoinTokens($tokens);
    }
    /**
     * Yields a list of tokens resulting from expansion.
     * 
     * This may be useful for WSGI apps.  NOTE: In the current implementation, the
     * entire expanded template must be stored memory.
     * 
     * NOTE: This is a generator, but JavaScript doesn't have generators.
     */
    function tokenstream($data_dict) {
        $tokens = [];
        $this->execute($data_dict, $tokens->append);
        foreach( $tokens as $token ) {
            yield($token);
        }
    }
}
/**
 * Trace of execution for JSON Template.
 * 
 * This object should be passed into the execute/expand() function.
 * 
 * Useful for debugging, especially for templates which reference other
 * templates.
 */
class Trace extends object {
    function __construct() {
        $this->exec_depth = 0;
        $this->template_depth = 0;
        $this->stack = [];
    }
    function Push($obj) {
        $this->stack[] = $obj;
    }
    function Pop() {
        $this->stack->pop();
    }
    function __str__() {
        return sprintf('Trace %s %s', [$this->exec_depth, $this->template_depth]);
    }
}
/**
 * Construct a dictinary { template name -> Template() instance }
 * 
 * Args:
 * root_section: _Section instance -- root of the original parse tree
 */
function _MakeGroupFromRootSection($root_section,$undefined_str) {
    $group = [];
    foreach( $root_section->Statements() as $statement ) {
        if (isinstance($statement, $basestring)) {
            continue;
        }
        list($func, $args) = $statement;
        if (($func == $_DoDef) && isinstance($args, _Section)) {
            $section = $args;
            $t = Template::_FromSection($section, $group, $undefined_str);
            $group[$section->section_name] = $t;
        }
    }
    return $group;
}
/**
 * Wire templates together so that they can reference each other by name.
 * 
 * This is a public API.
 * 
 * The templates becomes formatters with the 'template' prefix.  For example:
 * {var|template NAME} formats the node 'var' with the template 'NAME'
 * 
 * Templates may be mutually recursive.
 * 
 * This function *mutates* all the templates, so you shouldn't call it multiple
 * times on a single Template() instance.  It's possible to put a single template
 * in multiple groups by creating multiple Template() instances from it.
 * 
 * Args:
 * group: dictionary of template name -> compiled Template instance
 */
function MakeTemplateGroup($group) {
    foreach( $group->itervalues() as $t ) {
        $t->_UpdateTemplateGroup($group);
    }
}
/**
 * Join tokens (which may be a mix of unicode and str values).
 * 
 * See notes on unicode at the top.  This function allows mixing encoded utf-8
 * byte string tokens with unicode tokens.  (Python's default encoding is ASCII,
 * and we don't want to change that.)
 * 
 * We also want to support pure byte strings, so we can't get rid of the
 * try/except.  Two tries necessary.
 * 
 * If someone really wanted to use another encoding, they could monkey patch
 * jsontemplate.JoinTokens (this function).
 */
function JoinTokens($tokens) {
    try {
        return ''->join($tokens);
    }
    catch(UnicodeDecodeError $e) {
                return ''->join(/* py2php.fixme genexpr unsupported. */);
    }
}
/**
 * {.repeated section foo}
 */
function _DoRepeatedSection($args,$context,$callback,$trace) {
    $block = $args;
    $items = $context->PushSection($block->section_name, $block->pre_formatters);
    if ($items) {
        if (!(isinstance($items, $list))) {
            throw new new EvaluationError(sprintf('Expected a list; got %s', type($items)));
        }
        $last_index = (count($items) - 1);
        $statements = $block->Statements();
        $alt_statements = $block->Statements('alternates with');
        try {
            $i = 0;
            while (true) {
                $context->Next();
                _Execute($statements, $context, $callback, $trace);
                if (($i != $last_index)) {
                    _Execute($alt_statements, $context, $callback, $trace);
                }
                $i += 1;
            }
        }
        catch(StopIteration $e) {
                    }
    }
    else {
        _Execute($block->Statements('or'), $context, $callback, $trace);
    }
    $context->Pop();
}
/**
 * {.section foo}
 */
function _DoSection($args,$context,$callback,$trace) {
    $block = $args;
    if ($context->PushSection($block->section_name, $block->pre_formatters)) {
        _Execute($block->Statements(), $context, $callback, $trace);
        $context->Pop();
    }
    else {
        $context->Pop();
        _Execute($block->Statements('or'), $context, $callback, $trace);
    }
}
/**
 * {.predicate?}
 * 
 * Here we execute the first clause that evaluates to true, and then stop.
 */
function _DoPredicates($args,$context,$callback,$trace) {
    $block = $args;
    $value = $context->Lookup('@');
    foreach( $block->clauses as list(list($predicate, $args, $func_type), $statements) ) {
        if (($func_type == $ENHANCED_FUNC)) {
            $do_clause = $predicate($value, $context, $args);
        }
        else {
            $do_clause = $predicate($value);
        }
        if ($do_clause) {
            if ($trace) {
                $trace->Push($predicate);
            }
            _Execute($statements, $context, $callback, $trace);
            if ($trace) {
                $trace->Pop();
            }
            break;
        }
    }
}
/**
 * {.define TITLE}
 */
function _DoDef($args,$context,$callback,$trace) {
}
/**
 * Variable substitution, i.e. {foo}
 * 
 * We also implement template formatters here, i.e.  {foo|template bar} as well
 * as {.template FOO} for templates that operate on the root of the data dict
 * rather than a subtree.
 */
function _DoSubstitute($args,$context,$callback,$trace) {
    list($name, $formatters) = $args;
    if (($name == null)) {
        $value = $context->Root();
    }
    else {
        try {
            $value = $context->Lookup($name);
        }
        catch(TypeError $e) {
                        throw new new EvaluationError(sprintf('Error evaluating %r in context %r: %r', [$name, $context, $e]));
        }
    }
    $last_index = (count($formatters) - 1);
    foreach( enumerate($formatters) as list($i, list($f, $args, $formatter_type)) ) {
        try {
            if (($formatter_type == $TEMPLATE_FORMATTER)) {
                $template = $f->Resolve($context);
                if (($i == $last_index)) {
                    py2php_kwargs_method_call($template, 'execute', [$value,$callback], ["trace" => $trace]);
                    return;
                }
                else {
                    $tokens = [];
                    py2php_kwargs_method_call($template, 'execute', [$value,$tokens->append], ["trace" => $trace]);
                    $value = JoinTokens($tokens);
                }
            }
            else if (($formatter_type == $ENHANCED_FUNC)) {
                $value = $f($value, $context, $args);
            }
            else if (($formatter_type == $SIMPLE_FUNC)) {
                $value = $f($value);
            }
            else {
                assert(false, sprintf('Invalid formatter type %r', $formatter_type));
            }
        }
        catch(Exception $e) {
                        throw new Exception('py2php: python code would raise pre-existing exception here.');
        }
        catch(Exception $e) {
                        if (($formatter_type == $TEMPLATE_FORMATTER)) {
                throw new Exception('py2php: python code would raise pre-existing exception here.');
            }
            throw new py2php_kwargs_function_call('new EvaluationError', [sprintf('Formatting name %r, value %r with formatter %s raised exception: %r -- see e.original_exc_info', [$name, $value, $f, $e])], ["original_exc_info" => sys::exc_info()]);
        }
    }
    if (($value == null)) {
        throw new new EvaluationError(sprintf('Evaluating %r gave None value', $name));
    }
    $callback($value);
}
/**
 * Execute a bunch of template statements in a ScopedContext.
 * 
 * Args:
 * callback: Strings are "written" to this callback function.
 * trace: Trace object, or None
 * 
 * This is called in a mutually recursive fashion.
 */
function _Execute($statements,$context,$callback,$trace) {
    if ($trace) {
        $trace->exec_depth += 1;
    }
    foreach( enumerate($statements) as list($i, $statement) ) {
        if (isinstance($statement, $basestring)) {
            $callback($statement);
        }
        else {
            try {
                list($func, $args) = $statement;
                $func($args, $context, $callback, $trace);
            }
            catch(UndefinedVariable $e) {
                                $start = max(0, ($i - 3));
                $end = ($i + 3);
                $e->near = array_slice($statements, $start, $end - $start);
                $e->trace = $trace;
                throw new Exception('py2php: python code would raise pre-existing exception here.');
            }
        }
    }
}
/**
 * Free function to expands a template string with a data dictionary.
 * 
 * This is useful for cases where you don't care about saving the result of
 * compilation (similar to re.match('.*', s) vs DOT_STAR.match(s))
 */
function expand($template_str,$dictionary,$kwargs) {
    $t = py2php_kwargs_function_call('new Template', [$template_str], $kwargs);
    return $t->expand($dictionary);
}
/**
 * Takes a nested list structure and flattens it.
 * 
 * ['a', ['b', 'c']] -> callback('a'); callback('b'); callback('c');
 */
function _FlattenToCallback($tokens,$callback) {
    foreach( $tokens as $t ) {
        if (isinstance($t, $basestring)) {
            $callback($t);
        }
        else {
            _FlattenToCallback($t, $callback);
        }
    }
}
/**
 * OBSOLETE old API.
 */
function execute_with_style_LEGACY($template,$style,$data,$callback,$body_subtree='body') {
    try {
        $body_data = $data[$body_subtree];
    }
    catch(KeyError $e) {
                throw new new EvaluationError(sprintf('Data dictionary has no subtree %r', $body_subtree));
    }
    $tokens_body = [];
    $template->execute($body_data, $tokens_body->append);
    $data[$body_subtree] = $tokens_body;
    $tokens = [];
    $style->execute($data, $tokens->append);
    _FlattenToCallback($tokens, $callback);
}
/**
 * Expand a data dictionary with a template AND a style.
 * 
 * DEPRECATED -- Remove this entire function in favor of expand(d, style=style)
 * 
 * A style is a Template instance that factors out the common strings in several
 * "body" templates.
 * 
 * Args:
 * template: Template instance for the inner "page content"
 * style: Template instance for the outer "page style"  
 * data: Data dictionary, with a 'body' key (or body_subtree
 */
function expand_with_style($template,$style,$data,$body_subtree='body') {
    if ($template->has_defines) {
        return py2php_kwargs_method_call($template, 'expand', [$data], ["style" => $style]);
    }
    else {
        $tokens = [];
        py2php_kwargs_function_call('execute_with_style_LEGACY', [$template,$style,$data,$tokens->append], ["body_subtree" => $body_subtree]);
        return JoinTokens($tokens);
    }
}

