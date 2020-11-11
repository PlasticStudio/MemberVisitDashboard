<?php

use SilverStripe\ORM\DB;
use SilverStripe\Assets\Image;
use SilverStripe\Security\Group;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Security\Security;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Security\Permission;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\ORM\FieldType\DBDatetime;

class MemberExtension extends DataExtension {

	private static $db = [
		'ForeignID' => 'Varchar(128)',
		'ForcePasswordReset' => 'Boolean',
		'IsDisabled' => 'Boolean(false)',
		'IsFromCrm' => 'Boolean(false)',
		'LastImported' => 'Date'
	];

	private static $has_one = [
		'Image' => Image::class
	];

	private static $has_many = [
		'MemberVisits' => MemberVisit::class	
	];

	private static $belongs_many_many = [
		'Businesses' => Business::class
	];
		
	private static $owns = [
		'Image',
		'MemberVisits'
	];

	private static $summary_fields = [
		'FirstName' => 'First name',
		'Surname' => 'Surname',
		'Email' => 'Email',
		'BusinessesAsString' => 'Businessess',
		'IsDisabled' => 'Disabled?',
		'IsFromCrm' => 'CRM Record?'
	];

	/**
	 * Update CMS fields
	 **/
	public function updateCMSFields(FieldList $fields){

		$fields->removeByName('Businesses');
		$fields->removeByName('FileTracking');
		$fields->removeByName('LinkTracking');
		$fields->removeByName('IsFromCrm');

		$fields->addFieldToTab('Root.Main', CheckboxField::create('ForcePasswordReset', 'Force password reset')->setDescription('User will be forced to reset their password immediately'), 'Locale');
		$fields->addFieldToTab('Root.Main', CheckboxField::create('IsDisabled', 'Disabled')->setDescription('This value is auto-updated at the next CRM sync'), 'Locale');
		$fields->addFieldToTab('Root.Main', ListboxField::create('Businesses', 'Businesses', Business::get()->map('ID','Title','Please select')));

		$fields->addFieldToTab('Root.CRM', ReadonlyField::create('FromCrm', 'Member imported from the CRM', ($this->owner->IsFromCrm ? "Yes" : "No")));
		$fields->addFieldToTab('Root.CRM', ReadonlyField::create('ForeignID', 'Foreign ID'));
		$fields->addFieldToTab('Root.CRM', ReadonlyField::create('Created', 'Created'));
		$fields->addFieldToTab('Root.CRM', ReadonlyField::create('LastEdited', 'Last edited'));
		$fields->addFieldToTab('Root.CRM', ReadonlyField::create('LastImported', 'Last imported'));

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

	/**
	 * Define who can view/edit/create/delete this
	 * This should only be administrators or users with access to the Businesses section of the CMS
	 **/
	public function canView($member = null, $context = []){
		if(Permission::check('ADMIN')) return true;
		if(Permission::check('CMS_ACCESS_BusinessAdmin')) return true;
		return false;
	}
	public function canEdit($member = null, $context = []){
		if(Permission::check('ADMIN')) return true;
		if(Permission::check('CMS_ACCESS_BusinessAdmin')){

			// Business Admin only allows the management of NON-Administrator users
			return !$this->owner->inGroup('administrators');
		}
		return false;
	}
	public function canCreate($member = null, $context = []){
		if(Permission::check('ADMIN')) return true;

		// Creating members allows people to setup admin accounts, which is not desireable or secure
		if(Permission::check('CMS_ACCESS_BusinessAdmin')) return false;
		return false;
	}
	public function canDelete($member = null, $context = []){
		if(Permission::check('ADMIN')) return true;
		if(Permission::check('CMS_ACCESS_BusinessAdmin')){

			// Business Admin only allows the management of NON-Administrator users
			return !$this->owner->inGroup('administrators');
		}
		return false;
	}


	/**
	 * Can this user login
	 * This is where we run checks on valid accounts, not IsDisabled, etc
	 *
	 * @param $result ValidationResult 
	 * @return ValidationResult
	 **/
	public function canLogIn(ValidationResult $result = null){

		if ($this->owner->IsDisabled){
			$result->addError("This account is disabled. Please contact us if you feel this is in error.");
		}

		return $result;
	}


	/**
	 * Get this user's image
	 * We inherit the image from our first Member's Group that has an image
	 *
	 * @return Image
	 **/
	public function UserImage(){

		// We have our own, manually defined image
		if ($this->owner->ImageID > 0){
			return $this->owner->Image();
		}

		// Get the group that contains all member sub-groups
		$members_group = Group::get()->filter(['Code' => 'members'])->first();

		// Don't have one? It'll get created at the next sync...
		if (!$members_group){
			return null;
		}

		// Return the first one that has an image
		foreach ($this->owner->Groups()->sort('Title ASC') as $group){
			if ($group->ParentID == $members_group->ID && $group->ImageID > 0){
				return $group->Image();
			}
		}

		return null;
	}


	/**
	 * List of businesses converted to comma-separated string
	 *
	 * @return String
	 **/
	public function BusinessesAsString(){
		if (!$this->owner->Businesses() || $this->owner->Businesses()->count() <= 0){
			return "None";
		}

		$string = "";

		foreach ($this->owner->Businesses() as $business){
			if ($string !== ''){
				$string .= ', ';
			}
			$string.= $business->Title;
		}

		return $string;
	}
}

