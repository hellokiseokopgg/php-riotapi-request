<?php
	/**
	 * Created by PhpStorm.
	 * User: kargnas
	 * Date: 2017-06-30
	 * Time: 08:50
	 */

	namespace RiotQuest\Dto\Spectator;

	use RiotQuest\Dto\BaseDto;

	class CurrentGameParticipant extends BaseDto
	{
		/** @var double    The ID of the profile icon used by this participant */
		public $profileIconId;
		/** @var double    The ID of the champion played by this participant */
		public $championId;
		/** @var string    The summoner name of this participant */
		public $summonerName;
		/** @var Rune[]    The runes used by this participant */
		public $runes;
		/** @var boolean    Flag indicating whether or not this participant is a bot */
		public $bot;
		/** @var double    The team ID of this participant, indicating the participant's team */
		public $teamId;
		/** @var double    The ID of the second summoner spell used by this participant */
		public $spell2Id;
		/** @var Mastery[]    The masteries used by this participant */
		public $masteries;
		/** @var double    The ID of the first summoner spell used by this participant */
		public $spell1Id;
		/** @var double    The summoner ID of this participant */
		public $summonerId;
		/** @var Perk    The perks used by this participant */
		public $perks;

	}