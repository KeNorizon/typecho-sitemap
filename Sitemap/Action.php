<?php
class Sitemap_Action extends Typecho_Widget implements Widget_Interface_Do
{
	private $options;
	private $db;

	private $finalModifyTime = -1;

	private $articles;

	private static function outputItem($url, $lastModifyTime, $freq, $priority)
	{
		echo "\t<url>\n";
		echo "\t\t<loc>", $url, "</loc>\n";
		echo "\t\t<lastmod>", date('Y-m-d', $lastModifyTime), "</lastmod>\n";
		echo "\t\t<changefreq>", $freq, "</changefreq>\n";
		echo "\t\t<priority>", $priority, "</priority>\n";
		echo "\t</url>\n";
	}

	private function getCategoryLastModifyTime($mid)
	{
		$categoryListWidget = Typecho_Widget::widget('Widget_Metas_Category_List', 'current=' . $mid);

		$children = $categoryListWidget->getAllChildren($mid);
		$children[] = $mid;

		$relatedPost = $this->db->fetchRow($this->db->select()->from('table.contents')
			->join('table.relationships', 'table.contents.cid = table.relationships.cid')
			->where('table.relationships.mid IN ?', $children)
			->where('table.contents.type = ?', 'post')
			->where('table.contents.status = ?', 'publish')
			->where('table.contents.created < ?', $this->options->gmtTime)
			->where('table.contents.allowFeed = ?', 1)
			->order('table.contents.created', Typecho_Db::SORT_DESC)
			->limit(1));
		
		if ($relatedPost) {
			return $relatedPost['modified'];
		} else {
			return $this->finalModifyTime;
		}
	}

	private function getTagLastModifyTime($mid)
	{
		$relatedPost = $this->db->fetchRow($this->db->select()->from('table.contents')
			->join('table.relationships', 'table.contents.cid = table.relationships.cid')
			->where('table.relationships.mid = ?', $mid)
			->where('table.contents.type = ?', 'post')
			->where('table.contents.status = ?', 'publish')
			->where('table.contents.created < ?', $this->options->gmtTime)
			->where('table.contents.allowFeed = ?', 1)
			->order('table.contents.created', Typecho_Db::SORT_DESC)
			->limit(1));
		
		if ($relatedPost) {
			return $relatedPost['modified'];
		} else {
			return $this->finalModifyTime;
		}
	}

	private function outputIndex()
	{
		self::outputItem($this->options->index, $this->finalModifyTime, 'daily', '0.5');
	}

	private function outputPost($articles)
	{
		foreach ($articles as $article) {
			$article['slug'] = urlencode($article['slug']);
			$article['categories'] = $this->db->fetchAll($this->db->select()->from('table.metas')
				->join('table.relationships', 'table.relationships.mid = table.metas.mid')
				->where('table.relationships.cid = ?', $article['cid'])
				->where('table.metas.type = ?', 'category')
				->order('table.metas.order', Typecho_Db::SORT_ASC));
			$article['category'] = urlencode(current(Typecho_Common::arrayFlatten($article['categories'], 'slug')));
			$article['date'] = new Typecho_Date($article['created']);
			$article['year'] = $article['date']->year;
			$article['month'] = $article['date']->month;
			$article['day'] = $article['date']->day;
			
			$type = $article['type'];
			$routeExists = (null != Typecho_Router::get($type));
			$pathinfo = $routeExists ? Typecho_Router::url($type, $article) : '#';
			$permalink = Typecho_Common::url($pathinfo, $this->options->index);

			self::outputItem($permalink, $article['modified'], 'weekly', '0.5');
		}
	}

	private function outputPage($pages)
	{
		foreach ($pages as $page) {
			$page['slug'] = urlencode($page['slug']);

			$type = $page['type'];
			$routeExists = (null != Typecho_Router::get($type));
			$pathinfo = $routeExists ? Typecho_Router::url($type, $page) : '#';
			$permalink = Typecho_Common::url($pathinfo, $this->options->index);

			self::outputItem($permalink, $page['modified'], 'weekly', '0.5');
		}
	}

	private function outputCategory($categories)
	{
		foreach ($categories as $category) {
			$category['slug'] = urlencode($category['slug']);
			$category['directory'] = implode('/', array_map('urlencode', $category['directory']));

			$type = $category['type'];
			$routeExists = (null != Typecho_Router::get($type));
			$pathinfo = $routeExists ? Typecho_Router::url($type, $category) : '#';
			$permalink = Typecho_Common::url($pathinfo, $this->options->index);

			$modified = $this->getCategoryLastModifyTime($category['mid']);

			self::outputItem($permalink, $modified, 'daily', '0.5');
		}
	}

	private function outputTag($tags)
	{
		foreach ($tags as $tag) {
			$tag['slug'] = urlencode($tag['slug']);

			$type = $tag['type'];
			$routeExists = (null != Typecho_Router::get($type));
			$pathinfo = $routeExists ? Typecho_Router::url($type, $tag) : '#';
			$permalink = Typecho_Common::url($pathinfo, $this->options->index);

			$modified = $this->getTagLastModifyTime($tag['mid']);

			self::outputItem($permalink, $modified, 'daily', '0.5');
		}
	}

	public function action()
	{
		$this->db = Typecho_Db::get();
		$this->options = Typecho_Widget::widget('Widget_Options');

		// Query Data
		$pages = $this->db->fetchAll($this->db->select()->from('table.contents')
			->where('table.contents.status = ?', 'publish')
			->where('table.contents.created < ?', $this->options->gmtTime)
			->where('table.contents.type = ?', 'page')
			->where('table.contents.allowFeed = ?', 1)
			->order('table.contents.created', Typecho_Db::SORT_DESC));

		$articles = $this->db->fetchAll($this->db->select()->from('table.contents')
			->where('table.contents.status = ?', 'publish')
			->where('table.contents.created < ?', $this->options->gmtTime)
			->where('table.contents.type = ?', 'post')
			->where('table.contents.allowFeed = ?', 1)
			->order('table.contents.created', Typecho_Db::SORT_DESC));

		$categories = $this->db->fetchAll($this->db->select()->from('table.metas')
			->where('type = ?', 'category'));

		$categories = array_map(array(Typecho_Widget::widget('Widget_Metas_Category_List'), 'filter'), $categories);

		$tags = $this->db->fetchAll($this->db->select()->from('table.metas')
			->where('type = ?', 'tag'));

		if (count($articles) > 0) {
			$this->finalModifyTime = $articles[0]['modified'];
		} else {
			$this->finalModifyTime = time();
		}
	
		// Output
		ob_start();
		header("Content-Type: application/xml");

		echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
		echo "<?xml-stylesheet type=\"text/xsl\" href=\"" . $this->options->index . "/sitemap.xsl\"?>\n";
		echo "<urlset xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"\nxsi:schemaLocation=\"http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd\"\nxmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

		$this->outputIndex();
		$this->outputPage($pages);
		$this->outputPost($articles);
		$this->outputCategory($categories);
		$this->outputTag($tags);

		echo "</urlset>\n";
	}
}
