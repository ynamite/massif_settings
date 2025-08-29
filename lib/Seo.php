<?php

namespace Ynamite\MassifSettings;

use rex;
use rex_article;
use rex_clang;
use rex_extension;
use rex_extension_point;
use rex_media;
use rex_path;
use rex_yrewrite;

use function getimagesize;

class Seo
{

	public static function getTags()
	{

		$seo = new \Url\Seo();
		$manager = \Url\Url::resolveCurrent();
		$rewriter_seo = new YrewriteSeo();

		$image = 'opengraph.png';
		$logo = rex_yrewrite::getFullPath() . $image;

		$description = self::normalize($rewriter_seo->getDescription());

		if (!$manager) {
			rex_extension::register('YREWRITE_SEO_TAGS', function (rex_extension_point $ep) use ($rewriter_seo, $image, $description) {
				$tags = $ep->getSubject();

				$article_img = rex_article::getCurrent()->getValue('image');
				if ($article_img) {
					$image = $article_img;
				}
				$media = rex_media::get($image);
				$imageWidth = 0;
				$imageHeight = 0;
				if ($media) {
					$image = $media->getUrl();
					$imageWidth = $media->getWidth();
					$imageHeight = $media->getHeight();
				} else {
					$imageSize = @getimagesize(rex_path::frontend() . $image);
					$imageWidth = $imageSize[0] ?? 0;
					$imageHeight = $imageSize[1] ?? 0;
				}

				$hasImage = file_exists(rex_path::frontend() . $image);

				$tags['author'] = '<meta name="author" content="{{address_firma}}" />';
				$tags['creator'] = '<meta name="creator" content="Yves Torres, MASSIF Web Studio, www.massif.ch" />';
				$tags['publisher'] = '<meta name="publisher" content="{{address_firma}}" />';

				$tags['format-detection'] = '<meta name="format-detection" content="telephone=no, address=no, email=no" />';
				$tags['mobile-web-app-capable'] = '<meta name="mobile-web-app-capable" content="yes" />';
				$tags['mobile-web-app-status-bar-style'] = '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />';

				$tags['og:description'] = '<meta property="og:description" content="' . $description . '" />';
				$tags['og:url'] = '<meta property="og:url" content="' . self::normalize($rewriter_seo->getCanonicalUrl()) . '" />';
				$tags['og:site_name'] = '<meta property="og:site_name" content="' . $rewriter_seo->getTitle() . '" />';
				$tags['og:locale'] = '<meta property="og:locale" content="' . rex_clang::getCurrent()->getValue('code') . '" />';

				$tags['twitter:description'] = '<meta name="twitter:description" content="' . $description . '" />';
				$tags['twitter:url'] = '<meta name="twitter:url" content="' . self::normalize($rewriter_seo->getCanonicalUrl()) . '" />';
				$tags['twitter:card'] = '<meta name="twitter:card" content="summary_large_image" />';
				if ($hasImage) {
					$tags['image'] = '<meta name="image" content="' . $image . '" />';
					$tags['og:image'] = '<meta property="og:image" content="' . $image . '" />';
					$tags['og:image:width'] = '<meta property="og:image:width" content="' . $imageWidth . '" />';
					$tags['og:image:height'] = '<meta property="og:image:height" content="' . $imageHeight . '" />';
					$tags['twitter:image'] = '<meta name="twitter:image" content="' . $image . '" />';
				}
				ksort($tags);

				$ep->setSubject($tags);
			}, rex_extension::LATE);

			$tagsHtml = $rewriter_seo->getTags();
		} else {

			rex_extension::register('URL_SEO_TAGS', function (rex_extension_point $ep) use ($manager) {
				$tags = $ep->getSubject();

				$titleValues = [];
				$article = rex_article::get($manager->getArticleId());
				$title = strip_tags($tags['title']);

				if ($manager->getSeoTitle()) {
					$titleValues[] = $manager->getSeoTitle();
				}
				if ($article) {
					$domain = rex_yrewrite::getDomainByArticleId($article->getId());
					$title = $domain->getTitle();
					$titleValues[] = $article->getName();
				}
				if (count($titleValues)) {
					$title = rex_escape(str_replace('%T', implode(' / ', $titleValues), $title));
				}
				if ('' !== rex::getServerName()) {
					$title = rex_escape(str_replace('%SN', rex::getServerName(), $title));
				}

				$tags['title'] = sprintf('<title>%s</title>', $title);
				// order tags array by array keys
				ksort($tags);
				$ep->setSubject($tags);
			});

			$tagsHtml = $seo->getTags();

			$description = self::normalize($manager->getSeoDescription());
		}

		$full_url = rex_yrewrite::getFullPath();

		if (!rex::getProperty('req-with')) {

			$tagsHtml .= <<<EOT
			
				<script type="application/ld+json">
				{
					"@context": "http://schema.org",
					"@type": "LocalBusiness",
					"@id": "$full_url",
					"address": {
						"@type": "PostalAddress",
						"streetAddress": "{{address_strasse}}",
						"addressLocality": "{{address_ort}}",
						"postalCode": "{{address_plz}}",
						"addressRegion": "{{address_kanton_code}}",
						"addressCountry": "{{address_land_code}}"
					},
					"geo": {
						"@type": "GeoCoordinates",
						"latitude": "{{google_geo_lat}}",
						"longitude": "{{google_geo_long}}"
					},
					"description": "$description",
					"name": "{{address_firma}}",
					"url": "$full_url",
					"image": "$logo"
				}
				</script>

				<script type="application/ld+json">
				{
					"@context": "http://schema.org",
					"@type": "Organization",
					"url": "$full_url",
					"logo": "$logo"
				}
				</script>
				EOT;
		}


		return $tagsHtml;
	}

	protected static function normalize($string)
	{
		$string = rex_escape(strip_tags($string));
		return str_replace(["\n", "\r"], [' ', ''], $string);
	}
}
