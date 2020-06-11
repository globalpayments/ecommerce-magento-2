<?php

namespace RealexPayments\HPP\Model\Config\Source;

class ChallengePreference implements \Magento\Framework\Option\ArrayInterface
{
	const CHALLENGE_NO_PREFERENCE  = '01';
	const CHALLENGE_NO_CHALLENGE   = '02';
	const CHALLENGE_3DS_PREFERENCE = '03';
	const CHALLENGE_3DS_MANDATE    = '04';

	/**
	 * Possible settle modes.
	 *
	 * @return array
	 */
	public function toOptionArray()
	{
		return [
			[
				'value' => self::CHALLENGE_NO_PREFERENCE,
				'label' => 'No preference',
			],
			[
				'value' => self::CHALLENGE_NO_CHALLENGE,
				'label' => 'No challenge requested',
			],
			[
				'value' => self::CHALLENGE_3DS_PREFERENCE,
				'label' => 'Challenge requested: 3DS Requestor Preference',
			],
			[
				'value' => self::CHALLENGE_3DS_MANDATE,
				'label' => 'Challenge requested: Mandate',
			]
		];
	}
}
