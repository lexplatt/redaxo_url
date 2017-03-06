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
        if ($this->data->seoTitle)
        {
            \rex_extension::register('YREWRITE_TITLE', function ($params)
            {
                $subject = $params->getSubject();
                $subject = str_replace($params->getParam('title'), $this->normalizeMeta($this->data->seoTitle), $subject);
                return $subject;
            });
        }
        return $this->rewriterSeo->{$this->rewriter->getSeoTitleTagMethod()}();
    }

    public function getDescriptionTag()
    {
        if ($this->data->seoDescription)
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
        if ($this->data->fullUrl)
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

                foreach (\rex_clang::getAll() as $clang)
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
        return $this->rewriterSeo->{$this->rewriter->getSocialTagsMethod()}();
    }

    public function getImageTags()
    {
        if ($this->data->seoImg)
        {
            if (strpos($this->data->seoImg, ','))
            {
                $images = explode(',', $this->data->seoImg);
                $this->data->seoImg = array_shift($images);
            }
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
        return str_replace(["\n", "\r"], [' ', ''], $string);
    }

    protected function normalizeMeta($string)
    {
        return strtr(html_entity_decode(strip_tags($this->normalize($string))), ['"' => "'"]);
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
                    $lastmod = date(DATE_W3C, time());
                    if ($item->sitemapLastmod != '')
                    {
                        $id  = Generator::getId($item->fullUrl);
                        $sql = \rex_sql::factory();
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
                    }
                    $sitemap[] =
                        "\n" . '<url>' .
                        "\n" . '<loc>' . $item->fullUrl . '</loc>' .
                        "\n" . '<lastmod>' . $lastmod . '</lastmod>' .
                        "\n" . '<changefreq>' . $item->sitemapFrequency . '</changefreq>' .
                        "\n" . '<priority>' . $item->sitemapPriority . '</priority>' .
                        "\n" . '</url>';
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
