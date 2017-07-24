<?php
	/**
	 * Created by PhpStorm.
	 * User: kargnas
	 * Date: 2017-06-26
	 * Time: 03:51
	 */

	namespace RiotQuest;

	use RiotQuest\Dto\BaseArrayDto;
	use RiotQuest\Dto\BaseDto;
	use RiotQuest\Exception\RequestFailed\RiotAPICallException;
	use RiotQuest\Exception\UnknownException;
	use RiotQuest\RequestMethod\RequestMethodAbstract;
	use GuzzleHttp\Client;
	use GuzzleHttp\Exception\ConnectException;
	use GuzzleHttp\Exception\RequestException;
	use GuzzleHttp\Pool;
	use GuzzleHttp\Psr7\Response;

	/**
	 * CURL 의 multi request 기능을 통해서 비동기 콜을 한다. 단순히 http 에 대해서만 비동기로 실행된다. 콜백들은 비동기처럼 생겼지만, 실제로는 동기로 작동된다. (2개의 콜백이 동시에 작동되는 경우는 절대 없다. 무조건 하나 끝나야함 => PHP 의 특성)
	 * add 메소드를 통해서 리퀘스트를 추가할 수 있으며, 각 리퀘스트가 종료될 때 마다 onDone 혹은 onFail 이 실행된다.
	 *
	 * Class AsyncRiotAPI
	 * @package RiotQuest
	 */
	class AsyncRiotAPI
	{
		// Config
		const CONCURRENCY_ASYNC = 30;
		public $retryLimits = 5;
		public $requestTimeout = 10.0;
		public $userAgentString = "OP.GG API Client";

		// Member Variables
		protected $apiKey;

		/** @var bool 현재 Exec 작동중인지 여부 */
		private $isExecuting = false;

		/** @var AsyncRequest[] */
		protected $requests = [];


		function __construct($apiKey) {
			$this->apiKey = $apiKey;
		}

		/**
		 * @param                       $tried
		 * @param RequestException|null $requestException
		 *
		 * @return bool
		 */
		protected function shouldRetry($tried, RequestException $requestException = null) {
			if ($tried >= $this->retryLimits) {
				return false;
			}

			if ($requestException instanceof ConnectException) {
				return true;
			}

			if ($response = $requestException->getResponse()) {
				if ($response->getStatusCode() >= 500) {
					return true;
				}
			}

			return false;
		}

		public function getNewGuzzleClient() {
			return new Client([
				                  'timeout' => $this->requestTimeout
			                  ]);
		}

		/////////////////////////////
		/// Sync Call
		/**
		 * @param RequestMethodAbstract $requestMethod
		 *
		 * @return BaseDto|BaseArrayDto
		 */
		public function call(RequestMethodAbstract $requestMethod) {
			$result = null;

			$this->add($requestMethod, function ($dto) use (&$result) {
				$result = $dto;
			}, function (RiotAPICallException $exception) {
				throw $exception;
			})->exec();

			return $result;
		}
		///
		/////////////////////////////

		/////////////////////////////
		/// Async Call
		/**
		 * 비동기 콜에 새로운 Request 추가
		 *
		 * @param RequestMethodAbstract $requestMethod
		 * @param callable              $onDone 성공시 실행된다.
		 * @param callable|null         $onFail 실패시 실행된다. HTTP Status 200 이 아닐 때 발생한다.
		 *                                      RequestFailedException will be thrown If you do not make specific this callback.
		 *
		 * @return $this
		 */
		public function add(RequestMethodAbstract $requestMethod, callable $onDone, callable $onFail = null) {
			// Exec 작동중일때는 add 못하게 한다. 버그 방지.
			if ($this->isExecuting === true) {
				throw new UnknownException("You can't add the request when this instance executing. Please make new instance of " . get_class($this) . ".");
			}

			$guzzleRequest = $requestMethod->getRequest();
			$guzzleRequest = $guzzleRequest->withAddedHeader("X-Riot-Token", $this->apiKey);
			$guzzleRequest = $guzzleRequest->withAddedHeader("User-Agent", $this->userAgentString);

			$this->requests[] = new AsyncRequest($requestMethod, $guzzleRequest, $onDone, $onFail);
			return $this;
		}

		/**
		 * 비동기콜 한방에 모두 시작. 모든 콜 완료시 return void.
		 *
		 * @return void
		 */
		public function exec() {
			$client            = $this->getNewGuzzleClient();
			$this->isExecuting = true;

			// 실패한 리퀘스트는 다시 재시도한다.
			while(sizeof($this->requests) > 0) {
				$pool = new Pool($client,
				                 array_map(function (AsyncRequest $asyncRequest) use ($client) {
					                 // 익명함수 만들어야됨: 이해 안되면 guzzlehttp 문서 참조
					                 return (function () use ($asyncRequest, $client) {
						                 // 재시도인 경우에만 이벤트 호출
						                 if ($asyncRequest->tried >= 1) {
							                 EventDispatcher::fire(EventDispatcher::EVENT_REQUEST_RETRIED, [
								                 $asyncRequest->tried + 1,
								                 $asyncRequest->request
							                 ]);
						                 }
						                 return $asyncRequest->getPromise($client);
					                 });
				                 }, $this->requests), [
					                 'concurrency' => static::CONCURRENCY_ASYNC,
					                 'fulfilled'   => function (Response $response, $index) {
						                 $this->requests[$index]->tried++;
						                 $this->requests[$index]->markFinished = true;
						                 $this->requests[$index]->onDone($response);
					                 },
					                 'rejected'    => function (RequestException $requestException, $index) {
						                 $this->requests[$index]->tried++;

						                 // 재시도 할 필요 없으면, 실패 리퀘스트를 날려준다.
						                 if (!$this->shouldRetry($this->requests[$index]->tried, $requestException)) {
							                 $this->requests[$index]->markFinished = true;
							                 $this->requests[$index]->onFail($requestException);
						                 }
					                 }
				                 ]);

				$promise = $pool->promise();
				$promise->wait();

				$this->clearFinishedRequests();
			}

			$this->clear();
			$this->isExecuting = false;
		}

		protected function clearFinishedRequests() {
			$this->requests = array_filter($this->requests, function (AsyncRequest $request) {
				return !$request->markFinished;
			});
		}

		public function clear() {
			$this->requests = [];
		}
		///
		/////////////////////////
	}