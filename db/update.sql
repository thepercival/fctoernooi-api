-- PRE PRE PRE doctrine-update =============================================================
update plannings
set timeoutSeconds = 5
where timeoutSeconds > 30
  and state not in (1, 2, 4, 16);

-- POST POST POST doctrine-update ===========================================================




