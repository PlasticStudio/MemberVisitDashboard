<?php

namespace PlasticStudio\MemberVisits;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

class MemberVisit extends DataObject {	

	private static $singular_name = 'Member Visit';
	private static $plural_name = 'Member Visits';
	private static $description = 'Timestamp of login event for a member';
		
	private static $has_one = [
		'Member' => Member::class
	];
	
	private static $summary_fields = [
		'Created' => 'Date',
		'Member.Name' => 'Member'
	];
	
	private static $searchable_fields = [
		'Member.ID'
	];

    public function getCMSFields()
    {
		$fields = parent::getCMSFields();
		return $fields;
	}

	public function MemberName()
	{
		if ($member = $this->Member()) {
			return $member->getName();
		}
		return false;
	}

	public function MemberFirstVisit()
	{
		if ($member = $this->Member()) {
			return $member->getFirstLoggedIn();
		}
		return false;
	}

	public function MemberLastVisit()
	{
		if ($member = $this->Member()) {
			return $member->getLastLoggedIn();
		}
		return false;
	}

	public function MemberNumberOfVisits()
	{
		if ($member = $this->Member()) {
			return $member->getNumberOfVisits();
		}
		return false;
	}

	public function CMSEditLink() 
	{
		if ($member = $this->Member()) {
			return $member->Link();
		}
		return false;
	}

}