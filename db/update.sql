

-- PRE PRE PRE doctrine-update =============================================================
update sports set customId = 17 where name = 'padel';

drop table roundscoreconfigs;
drop table roundconfigs;
drop table sportscoreconfigs;
drop table sportconfigs;
drop table planninggameplaces;
drop table planninggames;
drop table gamescores;
drop table gameplaces;
drop table externalgames;
drop table games;

alter table lockerrooms rename lockerRooms;
alter table tournamentinvitations rename tournamentInvitations;
alter table tournamentusers rename tournamentUsers;
alter table teamplayers rename teamPlayers;
alter table teamcompetitors rename teamCompetitors;


-- POST POST POST doctrine-update ===========================================================




