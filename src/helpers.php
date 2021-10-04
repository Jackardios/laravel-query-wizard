<?php

use Illuminate\Support\Arr;

if (! function_exists('instance_of_one_of')) {
    /**
     * Checks if an object is an instance of one of the classes
     *
     * @param mixed $object
     * @param string|string[] $classes
     *
     * @return bool
     */
    function instance_of_one_of($object, $classes): bool
    {
        $classes = Arr::wrap($classes);

        foreach($classes as $class) {
            if ($object instanceof $class) {
                return true;
            }
        }
        
        return false;
    }
}
