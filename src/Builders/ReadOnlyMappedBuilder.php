<?php

namespace CodyJHeiser\Db2Eloquent\Builders;

use CodyJHeiser\Db2Eloquent\Exceptions\ReadOnlyModelException;

class ReadOnlyMappedBuilder extends MappedBuilder
{
    /**
     * @inheritdoc
     */
    public function insert(array $values)
    {
        throw ReadOnlyModelException::forModel(get_class($this->getModel()));
    }

    /**
     * @inheritdoc
     */
    public function update(array $values)
    {
        throw ReadOnlyModelException::forModel(get_class($this->getModel()));
    }

    /**
     * @inheritdoc
     */
    public function delete($id = null)
    {
        throw ReadOnlyModelException::forModel(get_class($this->getModel()));
    }

    /**
     * @inheritdoc
     */
    public function forceDelete()
    {
        throw ReadOnlyModelException::forModel(get_class($this->getModel()));
    }

    /**
     * @inheritdoc
     */
    public function upsert(array $values, $uniqueBy, $update = null)
    {
        throw ReadOnlyModelException::forModel(get_class($this->getModel()));
    }
}
