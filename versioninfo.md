#### 1.0.29

- fix formSubmit() was not properly processing translation result for inPlaceEdit() mode
- fix moved csrf meta from index.blade.php to layouts.master.blade.php so that all pages that extend layouts.master can use in-place-edit mode.
- move most of the details from the readme to the wiki.
- fix runtime exception if workbench projects are present but the config has an empty include for workbench config.
- fix replace deprecated \Route::after() 
- add key usage logging. Similar to missing keys except it logs keys that were accessed. 
