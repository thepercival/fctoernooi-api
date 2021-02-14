-- PRE PRE PRE doctrine-update =============================================================
update sports set name = lower(name);
insert into sports(name, team, customId, nrOfGamePlaces, gameMode ) values ('klaverjassen', false, 16, 4, 1 );
delete g from games g join poules p on p.id = g.pouleid join rounds r on r.id = p.roundid join roundnumbers rn on rn.id = r.numberid join competitions c on c.id = rn.competitionid where exists( select * from tournaments t where t.competitionid = c.id and t.updated = false );
delete from competitions where exists( select * from tournaments t where t.competitionid = competitions.id and t.updated = false );

-- from sports-planning
alter table planningfields rename planningFields;
alter table planningplaces rename planningPlaces;
alter table planninginputs rename planningInputs;
alter table planningpoules rename planningPoules;
alter table planningreferees rename planningReferees;
alter table planningsports rename planningSports;
update sports set customId = 15, gameMode = 1, nrOfGamePlaces = 1 where name = 'sjoelen';
delete from planningInputs;
-- from sports
alter table roundnumbers rename roundNumbers;
alter table planningconfigs rename planningConfigs;

-- POST POST POST doctrine-update ===========================================================
-- competitors 25952
update competitors c join associations a on a.id = c.associationid join leagues l on l.associationid = a.id join competitions comp on comp.leagueid = l.id join tournaments t on t.competitionid = comp.id
set c.tournamentId = t.id;

CREATE INDEX CDKTMP ON places(competitorid);

-- 23565
update places p join competitors c on c.id = p.competitorid join poules po on po.id = p.pouleid join rounds r on r.id = po.roundid join roundNumbers rn on rn.id = r.numberid and rn.number = 1 join tournaments t on t.competitionId = rn.competitionId set c.placeNr = p.number, c.pouleNr = po.number;

-- qualified places
-- update 	places p join poules po on po.id = p.pouleid join rounds r on r.id = po.roundid join roundnumbers rn on rn.id = r.numberid and rn.number > 1 join competitions c on c.id = rn.competitionid join tournaments t on t.competitionid = c.id
-- set 		p.qualifiedPlaceId = (
--     select 	pprev.id
--     from 	places pprev join poules poprev on poprev.id = pprev.pouleid join rounds rprev on rprev.id = poprev.roundid join roundnumbers rnprev on rnprev.id = rprev.numberid
--     where 	rnprev.number = rn.number-1 and pprev.competitorid = p.competitorid
-- )
-- where	t.updated = true;
update places p join poules po on po.id = p.pouleid join rounds r on r.id = po.roundid join roundNumbers rn on rn.id = r.numberid and rn.number > 1 join competitions c on c.id = rn.competitionid join tournaments t on t.competitionid = c.id set p.qualifiedPlaceId = ( select pprev.id from places pprev join poules poprev on poprev.id = pprev.pouleid join rounds rprev on rprev.id = poprev.roundid join roundNumbers rnprev on rnprev.id = rprev.numberid where rnprev.number = rn.number-1 and pprev.competitorid = p.competitorid );

ALTER TABLE places DROP INDEX CDKTMP;

-- from sports


-- ACC
-- delete from planninginputs where exists ( select * from plannings where timeoutSeconds < 0 and inputId = planninginputs.id );

-- delete from plannings;
