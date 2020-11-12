<?php

namespace PlasticStudio\MemberVisits;

use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Security;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\FieldType\DBDatetime;
use PlasticStudio\MemberVisits\MemberVisit;

class MemberExtension extends DataExtension 
{
	private static $has_many = [
		'MemberVisits' => MemberVisit::class	
	];
		
	private static $owns = [
		'MemberVisits'
	];

	public function updateCMSFields(FieldList $fields)
	{
		$fields->addFieldsToTab(
			'Root.MemberVisits',
			[
				ReadonlyField::create('FirstLoggedIn', 'First logged in', $this->getFirstLoggedIn()),
				ReadonlyField::create('LastVisited', 'Last visited', $this->getLastLoggedIn()),
				ReadonlyField::create('NumVisit', 'Number of visits', $this->getNumberOfVisits())
			],
			'MemberVisits'
		);
	}

	public function getFirstLoggedIn()
	{
		if ($visit = MemberVisit::get()->Filter('MemberID',$this->owner->ID)->Sort('Created ASC')->First()) {
			return $visit->Created;
		}
		return false;
	}

	public function getLastLoggedIn()
	{
		if ($visit = MemberVisit::get()->Filter('MemberID',$this->owner->ID)->Sort('Created DESC')->First()) {
			return $visit->Created;
		}
		return false;
	}

	public function getNumberOfVisits()
	{
		return MemberVisit::get()->Filter('MemberID',$this->owner->ID)->Count(); 
	}

	public function afterMemberLoggedIn() 
    {
        $this->logVisit();
    }

    public function memberAutoLoggedIn() 
    {
        $this->logVisit();
    }

	protected function logVisit() 
    {
		if(!Security::database_is_ready()) return;

		$visit = MemberVisit::create();
		$visit->Created = DBDatetime::now();
		$visit->MemberID = $this->owner->ID;
		$visit->Write();
    }
	
}