<?php

namespace Parley;

use ReflectionClass;
use Parley\Models\Thread;
use Parley\Support\Selector;

class ParleyManager
{
    /**
     * Create a new message thread, with an optional object reference
     *
     * @param  string $subject
     * @param  null $object
     * @return static
     */
    public function discuss($subject, $object = null)
    {
        $thread = Thread::create(['subject' => e($subject)]);

        // Set Thread Hash
        $thread->hash = \Hashids::encode($thread->id);

        if ($object) {
            $this->confirmObjectHasId($object);

            $thread->object_id = $object->id;
            $thread->object_type = get_class($object);
            $thread->save();
        }

        return $thread;
    }

    /**
     * Gather Threads for a group of objects
     *
     * @param null $options
     *
     * @return Selector
     */
    public function gather($options = null)
    {
        $data['type'] = 'any';

        if (is_array($options)) {
            foreach ($options as $key => $value) {
                $data[$key] = $value;
            }
        }

        return new Selector($data);
    }

    /**
     * Gather Open threads for a group of objects
     *
     * @return Selector
     */
    public function gatherOpen()
    {
        return $this->gather(['type' => 'open']);
    }

    /**
     * Gather Closed threads for a group of objects
     *
     * @return Selector
     */
    public function gatherClosed()
    {
        return $this->gather(['type' => 'closed']);
    }

    /**
     * Get a thread by its hash value
     * @param $hash
     *
     * @return mixed
     */
    public function getThread($hash)
    {
        return Thread::where('hash', $hash)->first();
    }

    /**
     * Confirm that an object has a valid Id field
     *
     * @param $object
     *
     * @return bool
     * @throws NonReferableObjectException
     */
    protected function confirmObjectHasId($object)
    {
        if (is_null($object->id)) {
            throw new NonReferableObjectException;
        }

        return true;
    }
}