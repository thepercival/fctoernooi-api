-- PRE PRE PRE doctrine-update =============================================================
alter table qualifyGroups
    CHANGE roundId parentRoundId int NOT NULL;

alter table rounds
    CHANGE parentQualifyId parentQualifyGroupId int NULL;

update tournaments
set createdDateTime = '2020-06-01'
where createdDateTime is null;

-- POST POST POST doctrine-update ===========================================================




