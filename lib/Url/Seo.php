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
    private $rewriter;

    private $rewriterSeo;

    private $dataId;

    public function __construct()
    {
        $this->rewriter    = Url::getRewriter();
        $this->rewriterSeo = $this->rewriter->getSeoInstance();
        $this->data        = Generator::getData();
        $this->dataId      = Generator::getId();
    }

    public function getTitleTag()
    {
        if ($this->isUrl())
        {
            \rex_extension::register('YREWRITE_TITLE', function ($params)
            {
                $subject = $params->getSubject();
                $subject = str_replace($params->getParam('title'), $this->getTitle(), $subject);
                return $subject;
            });
        }
        return $this->rewriterSeo->{$this->rewriter->getSeoTitleTagMethod()}();
    }

    public function getTitle()
    {
        return $this->normalizeMeta($this->data->seoTitle);
    }

    public function getDescriptionTag()
    {
        if ($this->isUrl())
        {
            \rex_extension::register('YREWRITE_DESCRIPTION', function ($params) {
                return $this->getDescription();
            });
        }
        return $this->rewriterSeo->{$this->rewriter->getSeoDescriptionTagMethod()}();
    }

    public function getDescription()
    {
        return $this->normalizeMeta($this->data->seoDescription);
    }

    public function getCanonicalUrlTag()
    {
        if ($this->isUrl())
        {
            \rex_extension::register('YREWRITE_CANONICAL_URL', function ($params)
            {
                return $this->data->fullUrl;
            });
        }
        return $this->rewriterSeo->{$this->rewriter->getSeoCanonicalTagMethod()}();
    }

    public function getCanonicalUrl()
    {
        return $this->rewriter->getFullPath(ltrim($this->data->url, "/"));
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
                    $subject[$clang->getCode()] = Generator::getUrlById($this->dataId, '', $clang->getId());
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

    public function getImg()
    {print_r($this->data);
        return $this->data->img;
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
        return htmlspecialchars(strip_tags($this->normalize($string)));
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
