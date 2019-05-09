<?php

/**
 * This file is part of the Url package.
 *
 * @author (c) Thomas Blum <thomas@addoff.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Url;

class UrlManagerSql
{
    const TABLE_NAME = 'url_generator_url';

    private $sql;

    private function __construct()
    {
        $this->sql = \rex_sql::factory();
        $this->sql->setTable(\rex::getTable(self::TABLE_NAME));
    }

    public static function factory()
    {
        return new self();
    }

    /**
     * @param int $id
     */
    public function setArticleId($id)
    {
        $this->sql->setValue('article_id', $id);
    }

    /**
     * @param int $id
     */
    public function setClangId($id)
    {
        $this->sql->setValue('clang_id', $id);
    }

    /**
     * @param int $id
     */
    public function setDataId($id)
    {
        $this->sql->setValue('data_id', $id);
    }

    /**
     * @param int $id
     */
    public function setProfileId($id)
    {
        $this->sql->setValue('profile_id', $id);
    }

    /**
     * @param array $value
     */
    public function setSeo(array $value)
    {
        $this->sql->setValue('seo', json_encode($value));
    }

    /**
     * @param bool $value
     */
    public function setSitemap($value)
    {
        $value = ($value === true) ? $value : false;
        $this->sql->setValue('sitemap', $value);
    }

    /**
     * @param bool $value
     */
    public function setStructure($value)
    {
        $this->sql->setValue('is_structure', $value);
    }

    /**
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->sql->setValue('url', $url);
    }

    /**
     * @param bool $value
     */
    public function setUserPath($value)
    {
        $this->sql->setValue('is_user_path', $value);
    }

    /**
     * @param string $value
     *
     * @throws \Exception
     */
    public function setLastmod($value = null)
    {
        if (!$value) {
            $value = time();
        }

        if (strpos($value, '-')) {
            // mysql date
            $datetime = new \DateTime($value);
            $value = $datetime->getTimestamp();
        }
        $this->sql->setValue('lastmod', date(DATE_W3C, $value));
    }

    /**
     * @throws \rex_sql_exception
     *
     * @return array
     */
    public function fetch()
    {
        return $this->sql->getArray();
    }

    /**
     * @return bool
     */
    public function save()
    {
        try {
            $this->sql->addGlobalCreateFields();
            $this->sql->addGlobalUpdateFields();
            $this->sql->insert();
            $success = true;
        } catch (\rex_sql_exception $e) {
            $success = false;
        }
        return $success;
    }

    /**
     * @throws \rex_sql_exception
     */
    public static function deleteAll()
    {
        $sql = self::factory();
        $sql->sql->setQuery('TRUNCATE TABLE '.\rex::getTable(self::TABLE_NAME));
    }

    /**
     * @param int $profileId
     *
     * @throws \rex_sql_exception
     */
    public static function deleteByProfileId($profileId)
    {
        $sql = self::factory();
        $sql->sql->setWhere('profile_id = ?', [$profileId]);
        $sql->sql->delete();
    }

    /**
     * @param int $profileId
     * @param int $datasetId
     *
     * @throws \rex_sql_exception
     */
    public static function deleteByProfileIdAndDatasetId($profileId, $datasetId)
    {
        $sql = self::factory();
        $sql->sql->setWhere('profile_id = ? AND data_id = ?', [$profileId, $datasetId]);
        $sql->sql->delete();
    }

    /**
     * @param int $profileId
     *
     * @throws \rex_sql_exception
     *
     * @return array
     */
    public static function getByProfileId($profileId)
    {

        $clangids = \rex_clang::getAllIds(true);

        $sql = self::factory();
        return $sql->sql->getArray('
            SELECT * 
            FROM '.\rex::getTable(self::TABLE_NAME).' AS m 
            LEFT JOIN '.\rex::getTable('article').' AS jt1 
                ON `jt1`.`id` = `m`.`article_id` 
                AND `jt1`.`clang_id` = `m`.`clang_id`
            WHERE 
                `m`.`profile_id` = ? 
                AND `m`.`clang_id` IN('. implode(',', $clangids) .') 
                AND `jt1`.`status` = 1', [$profileId]);
    }

    /**
     * @param UrlManager $manager
     * @param array      $clangIds
     *
     * @throws \rex_sql_exception
     *
     * @return array
     */
    public static function getHreflang(UrlManager $manager, $clangIds)
    {
        $where = implode(' OR ',
            array_map(function () {
                return '`clang_id` = ?';
            }, $clangIds)
        );
        $params = array_merge([
            $manager->getDatasetId(),
            $manager->getArticleId(),
            $manager->isUserPath() ? 1 : 0,
            $manager->isStructure() ? 1 : 0,
        ], $clangIds);

        $sql = self::factory();
        return $sql->sql->getArray('SELECT * FROM '.\rex::getTable(self::TABLE_NAME).' WHERE `data_id` = ? AND `article_id` = ? AND is_user_path = ? AND is_structure = ? AND ('.$where.')', $params);
    }

    /**
     * @param Profile $profile
     * @param int     $datasetId
     * @param int     $clangId
     *
     * @throws \rex_sql_exception
     *
     * @return array
     */
    public static function getOrigin(Profile $profile, $datasetId, $clangId)
    {
        $sql = self::factory();
        return $sql->sql->getArray('SELECT * FROM '.\rex::getTable(self::TABLE_NAME).' WHERE `profile_id` = ? AND `data_id` = ? AND `clang_id` = ? AND is_user_path = ? AND is_structure = ?', [$profile->getId(), $datasetId, $clangId, 0, 0]);
    }

    /**
     * @param Profile $profile
     * @param int     $datasetId
     * @param int     $clangId
     *
     * @throws \rex_sql_exception
     *
     * @return array
     */
    public static function getOriginAndExpanded(Profile $profile, $datasetId, $clangId)
    {
        $sql = self::factory();
        return $sql->sql->getArray('SELECT * FROM '.\rex::getTable(self::TABLE_NAME).' WHERE `profile_id` = ? AND `data_id` = ? AND `clang_id` = ?', [$profile->getId(), $datasetId, $clangId]);
    }

    /**
     * @param Url $url
     *
     * @throws \rex_sql_exception
     *
     * @return array
     */
    public static function getByUrl(Url $url)
    {
        $url->withScheme('');
        $url->withQuery('');
        $urlAsString = $url->__toString();

        $sql = self::factory();
        return $sql->sql->getArray('SELECT * FROM '.\rex::getTable(self::TABLE_NAME).' WHERE `url` = ?', [$urlAsString]);
    }
}
