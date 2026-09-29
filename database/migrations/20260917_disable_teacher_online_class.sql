-- Online-class activation and direct entry are not available to teacher accounts.
UPDATE dbadmin
SET teacher_perm_online_class = 0
WHERE adminrole = 3;
