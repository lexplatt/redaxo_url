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

use Url\Rewriter\Yrewrite;

class Generator
{
    protected $manager;

    public function __construct(ExtensionPointManager $manager)
    {
        $this->manager = $manager;
    }

    public function execute()
    {
        switch ($this->manager->getMode()) {
            case ExtensionPointManager::MODE_UPDATE_URL_ALL:
                UrlManagerSql::deleteAll();
                $profiles = Profile::getAll();
                if ($profiles) {
                    foreach ($profiles as $profile) {
                        $profile->buildUrls();
                    }
                }
                break;

            case ExtensionPointManager::MODE_UPDATE_URL_COLLECTION:
                $profiles = Profile::getByArticleId($this->manager->getStructureArticleId(), $this->manager->getStructureClangId());
                if ($profiles) {
                    foreach ($profiles as $profile) {
                        $profile->deleteUrls();
                        $profile->buildUrls();
                    }
                }
                break;

            case ExtensionPointManager::MODE_UPDATE_URL_DATASET:
                $profiles = Profile::getByTableName($this->manager->getDatasetTableName());
                if ($profiles) {
                    foreach ($profiles as $profile) {
                        $profile->deleteUrlsByDatasetId($this->manager->getDatasetPrimaryId());
                        $profile->buildUrlsByDatasetId($this->manager->getDatasetPrimaryId());
                    }
                }
                break;
        }
    }

    public static function boot()
    {
        if (null === Url::getRewriter()) {
            if (\rex_addon::get('yrewrite')->isAvailable()) {
                Url::setRewriter(new Yrewrite());
            } else {
                if (\rex_be_controller::getCurrentPage() == 'packages') {
                    \rex_extension::register('PAGE_TITLE_SHOWN', function (\rex_extension_point $ep) {
                        $ep->setSubject(\rex_view::error('<h4>Url Addon:</h4><p>Please install a rewriter addon or deactivate the Url AddOn.</p>'));
                    });
                }
            }
        }
    }

    public static function replaceLinks(\rex_extension_point $ep) {
        return preg_replace_callback(
            '@table://(\w+)-(\d+)(?:-(\d+))?/?@i',
            function ($matches) {
                $sql = \rex_sql::factory();
                $sql->setTable(\rex::getTable('url_generator_profile'));
                $sql->setWhere('table_name = :table AND(clang_id = :langId OR clang_id = 0)', [
                    'table'  => '1'. Database::DATABASE_TABLE_SEPARATOR . $matches[1],
                    'langId' => $matches[3],
                ]);
                $sql->select('article_id, clang_id, namespace');
                return rex_getUrl($sql->getValue('article_id'), $sql->getValue('clang_id'), [$sql->getValue('namespace') => $matches[2]]);
            },
            $ep->getSubject()
        );
    }
}
