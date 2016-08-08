<?php

namespace Md\Phunkie\Functions\show {

    use Md\Phunkie\Cats\Show;
    use Md\Phunkie\Types\Function1;
    use Md\Phunkie\Types\ImmList;
    use Md\Phunkie\Types\Option;
    use Md\Phunkie\Types\Pair;
    use Md\Phunkie\Types\Tuple;
    use Md\Phunkie\Types\Unit;
    use Md\Phunkie\Validation\Failure;
    use Md\Phunkie\Validation\Success;

    function show(...$values)
    {
        array_map(function($x) { echo get_value_to_show($x); }, $values);
    }

    function get_value_to_show($value) { switch (true) {
        case is_showable($value): return $value->show();
        case is_object($value) && method_exists($value, '__toString'): return (string)$value;
        case is_string($value): return $value == "\n" ? $value : '"' . $value . '"';
        case is_int($value):case is_double($value): case is_float($value): case is_long($value): return $value;
        case is_resource($value): return (string)$value;
        case is_bool($value): return $value ? 'true' : 'false';
        case is_null($value): return 'null';
        case is_array($value): return "[" . implode(",", array_map(function ($e) { return get_value_to_show($e); }, $value)) . "]";
        case is_callable($value): return "<function>";
        case is_object($value): return get_class($value) . "@" . substr(ltrim(spl_object_hash($value), "0"), 0, 7);
        default: return $value;}
    }

    function get_type_to_show($value) { switch (true) {
        case is_integer($value): return "Int";
        case is_float($value):
        case is_double($value): return "Double";
        case is_string($value): return "String";
        case is_resource($value): return "Resource";
        case is_bool($value): return "Boolean";
        case is_null($value): return "Null";
        case is_callable($value): return "Callable";
        case is_array($value): return "Array<" . get_collection_type($value) . ">";
        case is_object($value) && $value instanceof Unit: return "Unit";
        case is_object($value) && $value == None(): return "None";
        case is_object($value) && $value instanceof Option: return "Option<" . get_type_to_show($value->get()) . ">";
        case is_object($value) && $value instanceof Pair: return "(" . get_type_to_show($value->_1) . ", " . get_type_to_show($value->_2) . ")";
        case is_object($value) && $value instanceof ImmList: return "List<" . get_collection_type($value->toArray()) . ">";
        case is_object($value) && $value instanceof Success: return "Validation<E, " . get_type_to_show($value->getOrElse("")) . ">";
        case is_object($value) && $value instanceof Failure: return "Validation<" . get_type_to_show($value->fold(Function1::identity(),_)) . ", A>";
        case is_object($value) && $value instanceof Tuple:
            $types = [];
            for ($i = 1; $i <= $value->getArity(); $i++) {
                $types[] = get_type_to_show($value->{"_$i"});
            }
            return "(" . implode(", ", $types) . ")";
        case is_object($value): return get_class($value); }
    }

    function get_collection_type($value) { switch (count($value)) {
        case 0: return "Nothing";
        case 1: return get_type_to_show($value[0]);
        case 2: return combine_types(get_type_to_show($value[0]), get_type_to_show($value[1]));
        default: return combine_types(get_type_to_show($value[0]), get_collection_type(array_slice($value, 1))); }
    }

    function combine_types($a, $b) { switch (true) {
        case $a === $b: return $a;
        case $a === "Nothing": return $b;
        case $b === "Nothing": return $a;
        default: return "Mixed";}
    }

    function is_showable($value): bool
    {
        return !is_object($value) ? false : object_class_uses_trait($value, Show::class);
    }

    function object_class_uses_trait($object, $trait): bool
    {
        if (!is_object($object))  return false;

        $usesTrait = function ($c) use ($trait) { return in_array($trait, class_uses($c)); };

        $classAndParents = array_merge([get_class($object)], class_parents($object));

        $allParentsAndTraits = $classAndParents;
        array_map(function($x) use (&$allParentsAndTraits) {
            $allParentsAndTraits = array_merge($allParentsAndTraits, class_uses($x));
        }, $classAndParents);

        $countOfShowTraitUsage = array_sum(array_map($usesTrait, $allParentsAndTraits));

        return (bool)$countOfShowTraitUsage;
    }
}