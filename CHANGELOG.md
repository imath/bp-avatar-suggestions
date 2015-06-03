Versions
========

1.3
---

+ requires WordPress 4.2.
+ requires BuddyPress 2.3.
+ Uses the new BuddyPress Avatar UI by adding a new tav for suggestions.
+ Adds its own image size to match avatar full dimensions (these can > 150x150)
+ Uses the BuddyPress attachments API to improve the upload process and user feedbacks

1.2.1
-----

+ fixes a bug when the suggestion is set, props: lenasterg, pixie2012, lionel.
+ Make sure javascript is only loaded in the user's change profile photo screen, props: lenasterg.
+ improves the user feedback.

1.2
---

+ requires WordPress 4.1.
+ requires BuddyPress 2.2.
+ Uses a draft post to attach the avatar to.
+ Improves the interface so that it is now possible to bulk upload and delete avatar suggestions.
+ Improves the Avatar suggestions "selector", so that it can be used even if BuddyPress user uploads are disabled.
+ Adds support for Groups avatar suggestions.


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
