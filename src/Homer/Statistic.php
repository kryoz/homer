<?php
/* (c) Anton Medvedev <anton@elfet.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Homer;

class Statistic
{
    /**
     * @var \SplFixedArray
     */
    private $memory;
    /**
     * @var \SplFixedArray
     */
    private $connections;
    /**
     * @var \SplFixedArray
     */
    private $queue;
    
    private $max = 500;

    function __construct()
    {
        $this->memory = new \SplFixedArray($this->max);
        $this->connections = new \SplFixedArray($this->max);
        $this->queue = new \SplFixedArray($this->max);
    }


    public function getStats()
    {
        return [
            'memory' => $this->memory->toArray(),
            'connections' => $this->connections->toArray(),
            'queue' => $this->queue->toArray(),
        ];
    }

    public function add($name, $value)
    {
        /** @var \SplFixedArray $stat */
        $stat = $this->{$name};
        $key = $stat->key();
        $stat[$key] = $value;


        if ($stat->count() > $this->max) {
            unset($stat[0]);
        }

        $stat->next();
    }
}