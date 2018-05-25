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


class Seo
{
    /**
     * @var \Url\Rewriter\Rewriter
     */
    protected $rewriter;

    protected $rewriterSeo;

    protected $data;

    private $dataId;

    public function __construct()
    {
        $this->rewriter    = Url::getRewriter();
        $this->rewriterSeo = $this->rewriter->getSeoInstance();
        $this->data        = Generator::getData() ?: new \stdClass();
        $this->dataId      = Generator::getId();
    }

    public function getRewriterSeo()
    {
        return $this->rewriterSeo;
    }

    public function getTitleTag()
    {
        if (isset($this->data->seoTitle))
        {
            \rex_extension::register('YREWRITE_TITLE', function ($params)
            {
                $subject = $params->getSubject();
                $subject = str_replace('%T', $this->normalizeMeta($this->data->seoTitle), $subject);
                return $subject;
            });
        }
        return $this->rewriterSeo->{$this->rewriter->getSeoTitleTagMethod()}();
    }

    public function getDescriptionTag()
    {
        if (isset($this->data->seoDescription))
        {
            \rex_extension::register('YREWRITE_DESCRIPTION', function ($params)
            {
                return $this->normalizeMeta($this->data->seoDescription);
            });
        }
        return $this->rewriterSeo->{$this->rewriter->getSeoDescriptionTagMethod()}();
    }

    public function getCanonicalUrlTag()
    {
        if (isset($this->data->fullUrl))
        {
            \rex_extension::register('YREWRITE_CANONICAL_URL', function ($params)
            {
                return $this->data->fullUrl;
            });
        }
        return $this->rewriterSeo->{$this->rewriter->getSeoCanonicalTagMethod()}();
    }

    public function getHreflangTags()
    {
        if ($this->isUrl())
        {
            \rex_extension::register('YREWRITE_HREFLANG_TAGS', function ($params)
            {
                $subject = [];

                foreach (\rex_clang::getAll(true) as $clang)
                {
                    $article = \rex_article::get($this->data->articleId, $clang->getId());

                    if ($article->getValue('status') == 1)
                    {
                        $url = Generator::getUrlById($this->dataId, $this->data->articleId, $clang->getId(), TRUE, $this->data->urlParamKey);

                        // filter non existing urls - TODO: review
                        if (strlen($url) && !preg_match('!.*//$!', $url))
                        {
                            $subject[$clang->getCode()] = $url;
                        }
                    }
                }
                return $subject;
            });
        }
        return $this->rewriterSeo->{$this->rewriter->getSeoHreflangTagsMethod()}();
    }

    public function getRobotsTag()
    {
        return $this->rewriterSeo->{$this->rewriter->getSeoRobotsTagMethod()}();
    }

    public function getSocialTags()
    {
        \rex_extension::register('YREWRITE_FULL_URL', function ()
        {
            $artId = isset($this->data->articleId) ? $this->data->articleId : null;
            $urlPKey = isset($this->data->urlParamKey) ? $this->data->urlParamKey : null;
            $url = Generator::getUrlById($this->dataId, $artId, null, TRUE, $urlPKey);
            if($url)
                return $url;
        });
        return $this->rewriterSeo->{$this->rewriter->getSocialTagsMethod()}();
    }

    public function getImageTags()
    {
        $seoImage = '';

        if (property_exists($this->data, 'seoImg') && strlen($this->data->seoImg)) {
            $seoImage = $this->data->seoImg;
        }
        else if (property_exists($this->data, 'seoImage') && strlen($this->data->seoImage)) {
            $seoImage = $this->data->seoImage;
        }

        if (strlen($seoImage))
        {
            $images = explode(',', $seoImage);
            $this->data->seoImg = array_shift($images);

            \rex_extension::register('YREWRITE_IMAGE', function ($params)
            {
                return \rex_media::get($this->data->seoImg);
            });
        }
        return $this->rewriterSeo->{$this->rewriter->getImageTagsMethod()}();
    }

    protected function isUrl()
    {
        return ($this->dataId > 0);
    }

    protected function normalize($string)
    {
        return str_replace(["\n", "\r", "<br>", "<br/>", "<br />"], [' ', '', '; ', '; ', '; '], $string);
    }

    protected function normalizeMeta($string)
    {
        return html_entity_decode(strtr(strip_tags($this->normalize($string)), ['"' => "'", '&nbsp;' => ' ']));
    }

    public static function getSitemap()
    {
        $sitemap = [];
        $all     = Generator::getAll();
        if ($all)
        {
            foreach ($all as $item)
            {
                if ($item->sitemap)
                {
                    $images = [];
                    $lastmod = date(DATE_W3C, time());

                    if ($item->sitemapLastmod != '')
                    {
                        $media_cols = [];
                        $sql = \rex_sql::factory();
                        $id  = Generator::getId($item->fullUrl);
                        $sql->setQuery('SELECT ' . $item->sitemapLastmod . ' AS lastmod FROM ' . $item->table['name'] . ' WHERE ' . $item->table['id'] . ' = :id LIMIT 2', ['id' => $id]);
                        if ($sql->getRows() == 1)
                        {
                            $timestamp = $sql->getValue('lastmod');
                            if (strpos($timestamp, '-'))
                            {
                                // mysql date
                                $datetime  = new \DateTime($timestamp);
                                $timestamp = $datetime->getTimestamp();
                            }
                            $lastmod = date(DATE_W3C, $timestamp);
                        }

                        if (\rex_plugin::get('yform', 'manager')->isAvailable()) {
                            $ytable = \rex_yform_manager_table::get($item->table['name']);
                            $fields = $ytable->getValueFields();

                            foreach ($fields as $field) {
                                if ($field->getTypeName() == 'be_media') {
                                    $media_cols[] = $field->getName();
                                }
                            }
                        }

                        if (count($media_cols)) {
                            $query = '
                                SELECT 
                                    CONCAT_WS(",", '. implode(',', $media_cols) .') AS medialist 
                                 FROM ' . $item->table['name'] .' 
                                 WHERE '. $item->table['id'] .' = :id';
                            $sql->setQuery($query, ['id' => $id]);
                            $value = $sql->getValue('medialist');

                            if (strlen ($value)) {
                                $medias = array_unique(explode(',', $value));

                                foreach ($medias as $media_name) {
                                    $Rewriter = Url::getRewriter();
                                    $media    = \rex_media::get($media_name);

                                    if ($media && in_array($media->getExtension(), ['png', 'jpg', 'jpeg', 'gif'])) {
                                        $img_url  = $Rewriter->getFullPath(ltrim(\rex_url::media($media_name), '/'));
                                        $images[] = \rex_extension::registerPoint(new \rex_extension_point('URL_SITEMAP_IMAGE',
                                            "\n<image:loc>" . $img_url . '</image:loc>'.
                                            "\n<image:title>" . strtr($media->getValue('title'), ['&' => '&amp;']) . '</image:title>', ['media' => $media, 'img_url' => $img_url, 'lang_id' => $item->clangId]));
                                    }
                                }
                            }
                        }
                    }


                    $_url =
                        "\n" . '<url>' .
                        "\n" . '<loc>' . $item->fullUrl . '</loc>' .
                        "\n" . '<lastmod>' . $lastmod . '</lastmod>' .
                        "\n" . '<changefreq>' . $item->sitemapFrequency . '</changefreq>' .
                        "\n" . '<priority>' . $item->sitemapPriority . '</priority>';

                    if (count($images)) {
                        if (count($images)) {
                            $_url .= "\n<image:image>". implode("</image:image>\n<image:image>", $images) .'</image:image>';
                        }
                    }

                    $_url .= "\n" . '</url>';
                    $sitemap[] = $_url;

                    if (count($item->fullPathNames))
                    {
                        foreach ($item->fullPathNames as $path)
                        {
                            $sitemap[] =
                                "\n" . '<url>' .
                                "\n" . '<loc>' . $path . '</loc>' .
                                "\n" . '<lastmod>' . $lastmod . '</lastmod>' .
                                "\n" . '<changefreq>' . $item->sitemapFrequency . '</changefreq>' .
                                "\n" . '<priority>' . $item->sitemapPriority . '</priority>' .
                                "\n" . '</url>';
                        }
                    }
                    if (count($item->fullPathCategories))
                    {
                        foreach ($item->fullPathCategories as $path)
                        {
                            $sitemap[] =
                                "\n" . '<url>' .
                                "\n" . '<loc>' . $path . '</loc>' .
                                "\n" . '<lastmod>' . $lastmod . '</lastmod>' .
                                "\n" . '<changefreq>' . $item->sitemapFrequency . '</changefreq>' .
                                "\n" . '<priority>' . $item->sitemapPriority . '</priority>' .
                                "\n" . '</url>';
                        }
                    }
                }
            }
        }

        return $sitemap;
    }
}
