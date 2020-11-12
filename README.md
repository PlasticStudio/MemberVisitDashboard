# MemberVisitDashboard
This plugin adds a CMS report to Silverstripe 4 websites to display member login data.

Each time a member logs in to the website, a record is created that has a has_one relationship to the Member record.

These records are displayed in a reporting dashboard (available in `your-website-domain/admin/reports/`), which can be filtered by date range and Member name.

Member visit records are also displayed on each Member record in teh Security section (`your-website-domain/admin/security/`).


# Requirements

* SilverStripe 4