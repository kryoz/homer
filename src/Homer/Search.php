<?php
/* (c) Anton Medvedev <anton@elfet.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Homer;


class Search
{
    /**
     * @var \PDO
     */
    private $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function search($text, $start, $limit)
    {
        $query = $this->db->prepare("SELECT * FROM indexes WHERE tsv @@ plainto_tsquery(:text) LIMIT :limit OFFSET :start");
        $query->bindValue(':text', $text);
        $query->bindValue(':start', $start);
        $query->bindValue(':limit', $limit);
        $query->execute();

        return $query->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getCount($text)
    {
        $query = $this->db->prepare("SELECT count(*) FROM indexes WHERE tsv @@ plainto_tsquery(:text)");
        $query->bindValue(':text', $text);
        $query->execute();
        $result = $query->fetchAll(\PDO::FETCH_COLUMN, 0);
        return !empty($result) ? $result[0] : 0;
    }

    public function highlight($body, $word)
    {
        $wrapLength = 100;
        $normalizedBody = mb_strtolower($body);
        $word = mb_strtolower($word);
        while (($pos = mb_strpos($normalizedBody, $word)) === false) {
            $len = mb_strlen($word);
            $word = mb_substr($word, 0, $len-1);
        }

        $body = '...' . mb_substr($body, $pos - $wrapLength, 2 * $wrapLength + $len) . '...';
        return preg_replace('~(' . $word . ')~uis', '<b>$1</b>', $body);
    }
}