Versions
========

1.1
---

+ requires BuddyPress 2.0


1.1-beta2
---------

+ adds a filter to the avatar lists just before displaying it to the user 
+ example of use : `add_filter( 'bp_as_filter_avatar_list', 'filter_list_by_xprofile_gender', 10, 1 );`


1.1-beta1
---------

+ fixes a bug that used to appear when uploading a new avatar while a suggested avatar was set.
+ fixes the missing word 'of' in the alternative text
+ adds a functionnality to delete the suggested avatar of users if the suggested avatar has been deleted in backend by admin
+ replaces the action button from activate to deactivate once the user selected their avatar (it's no more needed to refresh the page to do so)


1.0-beta1
---------

+ well that's the first version !