<?php

use SilverStripe\Dev\Debug;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Reports\Report;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HeaderField;
use SilverStripe\View\Requirements;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\ToggleCompositeField;

class CustomSideReport_MemberVisits extends Report 
{
    public function title() 
    {
        return 'Member login activity';
    }

    public function columns() 
    {
        return [
            'Created' => 'Visit',
            'MemberName' => [
                'title' => 'Member name',
                'formatting' => function ($value, $item) {
                    $memberID = $item->MemberID;
                    $memberName = $item->MemberName();
                    return sprintf(
                        '<a href="/admin/security/EditForm/field/Members/item/%s/edit">%s</a>',
                        $memberID,
                        $memberName
                    );
                }
            ],
            'MemberFirstVisit' => 'First logged in',
            'MemberLastVisit' => 'Last visit'
        ];
    }
    
    public function sourceRecords($params)
    {
        $records = ArrayList::create();
        $filter = [];
        if (isset($params['Start']) && !empty($params['Start'])) {
            $filter['Created:GreaterThanOrEqual'] = $params['Start'] . ' 00:00:00';
        }
        if (isset($params['End']) && !empty($params['End'])) {
            $filter['Created:LessThanOrEqual'] = $params['End'] . ' 23:23:59';
        }
        if (isset($params['FirstName']) && !empty($params['FirstName'])) {
            $filter['Member.FirstName:PartialMatch'] = $params['FirstName'];
        }
        if (isset($params['LastName']) && !empty($params['LastName'])) {
            $filter['Member.Surname:PartialMatch'] = $params['LastName'];
        }
        
        foreach (MemberVisit::get()->Filter($filter) as $record) {
            $record->MemberName = $record->MemberName();
            $record->MemberFirstVisit = $record->MemberFirstVisit();
            $record->MemberLastVisit = $record->MemberLastVisit();
            $records->push($record);
        }

        if (isset($params['Unique'])) {
            $records->removeDuplicates('MemberID');
        }

        $sortField = 'Created';
        $sortDirection = 'DESC';
        if (isset($params['Sort'])) {
            $sortField = $params['Sort'];
        }
        if (isset($params['Direction'])) {
            $sortDirection = $params['Direction'];
        }
        return $records->sort($sortField, $sortDirection);

    }

    public function parameterFields()
    {
        return FieldList::create(
            FieldGroup::create(
                'Filter by date range',
                DateField::create('filters[Start]', 'Start')->addExtraClass('no-change-track'),
                DateField::create('filters[End]', 'End')->addExtraClass('no-change-track')
            ),
            FieldGroup::create(
                'Filter by member',
                TextField::create('filters[FirstName]', 'First name')->addExtraClass('no-change-track'),
                TextField::create('filters[LastName]', 'Last name')->addExtraClass('no-change-track')
            ),
            CheckboxField::create('Unique', 'Only show unique member records'),
            FieldGroup::create(
                'Sort records',
                DropdownField::create(
                    'filters[Sort]', 
                    'Sort by',
                    [
                        'Created' => 'Visit',
                        'MemberName' => 'Member name',
                        'MemberFirstVisit' => 'Member first logged in',
                        'MemberLastVisit' => 'Member last visit',
                        //'MemberNumberOfVisits' => 'Member total visits'
                    ]
                )->addExtraClass('no-change-track'),
                DropdownField::create(
                    'filters[Direction]', 
                    'Direction',
                    [
                        'DESC' => 'Descending',
                        'ASC' => 'Ascending'
                    ]
                )->addExtraClass('no-change-track')
            )
            
        );
    }

    public function getCMSFields()
    {
        Requirements::css('app/cms/member-visits-dashboard.css');
        $fields = new FieldList();

        if ($description = $this->description()) {
            $fields->push(new LiteralField('ReportDescription', "<p>" . $description . "</p>"));
        }

        if ($this->hasMethod('parameterFields') && $parameterFields = $this->parameterFields()) {
            $filter_fields = [];
            foreach ($parameterFields as $field) {
                // Namespace fields for easier handling in form submissions
                $field->setName(sprintf('filters[%s]', $field->getName()));
                $field->addExtraClass('no-change-track'); // ignore in changetracker
                $filter_fields[] = $field;
            }

            // Add a search button
            $formAction = FormAction::create('updatereport', _t('SilverStripe\\Forms\\GridField\\GridField.Filter', 'Filter'));
            $formAction->addExtraClass('btn-primary mb-4');
            $filter_fields[] = $formAction;
        }
        $compositeField = ToggleCompositeField::create(
            'FilterFields',
            'Member login activity',
            $filter_fields
        )->addExtraClass('member-visits filter-fields');

        $fields->push($compositeField);

        $fields->push($this->getStatsFields());

        $fields->push($this->getReportField());

        $this->extend('updateCMSFields', $fields);

        return $fields;
    }

    public function getStatsFields()
    {
        $params = $this->getSourceParams();
        $sourceRecords = $this->sourceRecords($params);
        return FieldGroup::create(
            LiteralField::create('stats', '<div class="stats">'),
            LiteralField::create('statsfields', '<div class="stats-fields">'),
            $this->getTotalCountField($sourceRecords),
            $this->getUniqueCountField($sourceRecords),
            $this->getFirstTimeVisitCountField($sourceRecords),
            LiteralField::create('stats-fields-end', '</div>'),
            LiteralField::create('stats-end', '</div>')
        )->addExtraClass('member-visits stats-dashboard');
    }

    public function getTotalCountField($sourceRecords)
    {
        return LiteralField::create(
            'TotalCount',
            '<div class="stat-block total-count">
                <h3 class="stat-title grey-text">Total visits</h3>
                <span class="stat total-visits grey-text">'.$sourceRecords->count().'</span>
            </div>'
        );
    }

    public function getUniqueCountField($sourceRecords)
    {
        return LiteralField::create(
            'TotalCount',
            '<div class="stat-block total-count">
                <h3 class="stat-title grey-text">Total visitors</h3>
                <span class="stat total-visitors grey-text">'.$sourceRecords->removeDuplicates('MemberID')->count().'</span>
            </div>'
        );
    }

    public function getFirstTimeVisitCountField($sourceRecords)
    {
        $filter = [];
        if (isset($params['Start'])) {
            $filter['MemberFirstVisit:GreaterThanOrEqual'] = $params['Start'] . ' 00:00:00';
        }
        if (isset($params['End'])) {
            $filter['MemberFirstVisit:LessThanOrEqual'] = $params['End'] . ' 23:23:59';
        }
        return LiteralField::create(
            'TotalCount',
            '<div class="stat-block total-first-logins">
                <h3 class="stat-title grey-text">First time visitors</h3>
                <span class="stat total-first-logins green-text">'.$sourceRecords->Filter($filter)->removeDuplicates('MemberID')->count().'</span>
            </div>'
        );
    }

}