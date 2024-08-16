<?php

namespace A17\EdgeFlush\Behaviours;

use A17\EdgeFlush\Support\Helpers;
use Illuminate\Database\Eloquent\Model;

trait CastObject
{
    public function getInternalModel(Model $model): Model
    {
        $internal = Helpers::configString('edge-flush.tags.external-models.'.get_class($model));

        if (blank($internal)) {
            return $model;
        }

        return $this->castObject($internal, $model);
    }

    /**
     * Class casting
     *
     * @param string|object $destination
     * @param object $source
     * @return object
     */
    public function castObject(object|string $destination, object $source)
    {
        if (is_string($destination)) {
            $destination = app($destination);
        }

        $sourceReflection = new \ReflectionObject($source);

        $destinationReflection = new \ReflectionObject($destination);

        $sourceProperties = $sourceReflection->getProperties();

        foreach ($sourceProperties as $sourceProperty) {
            $sourceProperty->setAccessible(true);

            $name = $sourceProperty->getName();

            $value = $sourceProperty->getValue($source);

            if ($destinationReflection->hasProperty($name)) {
                $propDest = $destinationReflection->getProperty($name);

                $propDest->setAccessible(true);

                $propDest->setValue($destination,$value);
            } else {
                $destination->$name = $value;
            }
        }

        return $destination;
    }
}
