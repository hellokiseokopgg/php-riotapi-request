<?php
	/**
	 * Created by PhpStorm.
	 * User: kargnas
	 * Date: 2017-07-04
	 * Time: 19:11
	 */

	namespace RiotQuest\RequestMethod\LolStaticData;

	use RiotQuest\Constant\EndPoint;
	use RiotQuest\Dto\LolStaticData\ProfileIcon\ProfileIconDataDto;
	use RiotQuest\RequestMethod\Request;
	use RiotQuest\RequestMethod\RequestMethodAbstract;
	use GuzzleHttp\Psr7\Response;
	use JsonMapper;

	class ProfileIcons extends RequestMethodAbstract
	{
		public $path = EndPoint::LOL_STATIC_DATA__PROFILE_ICONS;

		/** @var string */
		public $locale, $version;

		public function getRequest() {
			$uri = "https://" . $this->platform->apiHost . "" . $this->path;

			$query = static::buildParams([
				                             'locale'  => $this->locale,
				                             'version' => $this->version
			                             ]);

			if (strlen($query) > 0) {
				$uri .= "?{$query}";
			}
			return $this->getPsr7Request('GET', $uri);
		}

		public function mapping(Response $response) {
			$json = \GuzzleHttp\json_decode($response->getBody());

			$mapper = new JsonMapper();
			return $mapper->map($json, new ProfileIconDataDto());
		}
	}