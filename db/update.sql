

update tournaments set public = 1;

update sponsors set screennr = 1;

select *, (select count(*) from sponsors where tournamentid = tournaments.id and screennr = 1 ) as nrofsponsors from tournaments where (select count(*) from sponsors where tournamentid = tournaments.id and screennr = 1 ) > 9;

select id, screennr, tournamentid from sponsors where tournamentid in ( 20, 776 );



update sponsors set screennr = 2 where id in  ( 13, 14, 15 );

update sponsors set screennr = 2 where id in  ( 125, 126, 127, 128, 129 );