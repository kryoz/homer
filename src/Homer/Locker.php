<?php
/* (c) Anton Medvedev <anton@elfet.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Homer;

class Locker
{
    private $locks = array();

    public function lock($thing)
    {
        $this->locks[$thing] = time();
    }

    public function isAvailable($thing)
    {
        return !isset($this->locks[$thing]);
    }

    public function release($max)
    {
        while($time = reset($this->locks)) {
            if(time() - $time > $max) {
                unset($this->locks[key($this->locks)]);
            } else {
                break;
            }
        }
    }
}