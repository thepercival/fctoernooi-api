select * from tournaments t join competitions c on c.id = t.competitionid join leagues l on l.id = c.leagueid where l.name like '%Flater%';

-- PRE PRE PRE doctrine-update =============================================================

delete g from games g join poules p on p.id = g.pouleid join rounds r on r.id = p.roundid join roundnumbers rn on rn.id = r.numberid join competitions c on c.id = rn.competitionid where exists( select * from tournaments t where t.competitionid = c.id and t.updated = false );
delete from competitions where exists( select * from tournaments t where t.competitionid = competitions.id and t.updated = false );

-- from sports-planning
SET FOREIGN_KEY_CHECKS = 0;
truncate planninggameplaces;
truncate planninggames;
truncate planningfields;
truncate planningplaces;
truncate planningpoules;
truncate planningreferees;
truncate planningsports;
truncate plannings;
truncate planninginputs;
SET FOREIGN_KEY_CHECKS = 1;

alter table planningfields rename planningFields;
alter table planningplaces rename planningPlaces;
alter table planninginputs rename planningInputs;
alter table planningpoules rename planningPoules;
alter table planningreferees rename planningReferees;
alter table planningsports rename planningSports;

-- from sports
alter table roundnumbers rename roundNumbers;
alter table planningconfigs rename planningConfigs;

update competitors set registered = 0 where registered is null;

-- POST POST POST doctrine-update ===========================================================
CREATE INDEX CDKTMP ON places(competitorid);
CREATE INDEX CDKTMP2 ON competitors(name);

update fields f join sportconfigs sc on sc.id = f.sportConfigId set competitionSportId = ( select id from competitionSports where competitionId = sc.competitionId );

delete from competitors where not exists ( select * from places where competitorid = competitors.id );
update competitors c set name = substr(name, 0, 28 ) where exists( select * from competitors csub where csub.id <> c.id and csub.name = c.name and csub.associationId = c.associationId ) and length(c.name) > 28;
update	competitors c set c.name = concat( c.name, '#', (select count(*) from competitors csub where csub.id < c.id and csub.name = c.name and csub.associationId = c.associationId) + 1 ) where ( select count(*) from competitors csub where csub.id < c.id and csub.name = c.name and csub.associationId = c.associationId ) > 0;
-- CHECK -- select * from competitors c where exists( select * from competitors csub where csub.id <> c.id and csub.name = c.name and csub.associationId = c.associationId ) order by c.name;

-- remove orphans
delete from competitors where not exists ( select * from places where competitorid = competitors.id );
delete from competitions where not exists ( select * from tournaments where competitionId = competitions.id );
delete from leagues where not exists ( select * from competitions where leagueid = leagues.id );
delete from associations where not exists ( select * from competitors where associationid = associations.id ) and not exists ( select * from leagues where associationid = associations.id );
delete from associations where not exists ( select * from leagues where associationid = associations.id );

-- STAP 1 : kopieer alle associations die meerdere leagues hebben
-- select *, ( select count(*) from leagues lsub where lsub.associationid = a.id ) from competitions c join leagues l on l.id = c.leagueid join associations a on a.id = l.associationid where ( select count(*) from leagues lsub where lsub.associationid = a.id ) > 1 order by a.id
-- select *, ( select count(*) from leagues lsub where lsub.associationid = a.id ) from competitions c join leagues l on l.id = c.leagueid join associations a on a.id = l.associationid where exists( select * from leagues lsub where lsub.id < l.id and lsub.associationid = l.associationid ) order by a.id
insert into associations( name )
    (
        select concat(a.name,'-',l.id) from leagues l join associations a on a.id = l.associationid where exists( select * from leagues lsub where lsub.id < l.id and lsub.associationid = l.associationid )
    );
-- STAP 3 : update alle leagues die dezelfde association hebben
update leagues l join associations a on a.id = l.associationid set associationid = ( select id from associations where name = concat(a.name,'-',l.id) ) where exists( select * from leagues lsub where lsub.id < l.id and lsub.associationid = l.associationid );

-- competitors 25952
-- delete from competitions where id = 688; -- guust flater(raar)
-- update tournamentusers set userid = 5 where tournamentid in (56, 609)
update competitors c join associations a on a.id = c.associationid join leagues l on l.associationid = a.id join competitions comp on comp.leagueid = l.id join tournaments t on t.competitionid = comp.id
set c.tournamentId = t.id;
-- CONTROLE: select * from competitors where tournamentid is null;

-- 23565
update places p join competitors c on c.id = p.competitorid join poules po on po.id = p.pouleid join rounds r on r.id = po.roundid join roundNumbers rn on rn.id = r.numberid and rn.number = 1 join tournaments t on t.competitionId = rn.competitionId set c.placeNr = p.number, c.pouleNr = po.number;
-- CONTROLE: select * from competitors where placeNr = 0 or pouleNr = 0;
-- ER ZIJN DUS NOG COMPETITORS DIE NIET GEKOPPELD ZIJN, KIJK WAAROM

-- qualified places
-- update 	places p join poules po on po.id = p.pouleid join rounds r on r.id = po.roundid join roundnumbers rn on rn.id = r.numberid and rn.number > 1 join competitions c on c.id = rn.competitionid join tournaments t on t.competitionid = c.id
-- set 		p.qualifiedPlaceId = (
--     select 	pprev.id
--     from 	places pprev join poules poprev on poprev.id = pprev.pouleid join rounds rprev on rprev.id = poprev.roundid join roundnumbers rnprev on rnprev.id = rprev.numberid
--     where 	rnprev.number = rn.number-1 and pprev.competitorid = p.competitorid
-- );
update places p join poules po on po.id = p.pouleid join rounds r on r.id = po.roundid join roundNumbers rn on rn.id = r.numberid and rn.number > 1 join competitions c on c.id = rn.competitionid join tournaments t on t.competitionid = c.id set p.qualifiedPlaceId = ( select pprev.id from places pprev join poules poprev on poprev.id = pprev.pouleid join rounds rprev on rprev.id = poprev.roundid join roundNumbers rnprev on rnprev.id = rprev.numberid where rnprev.number = rn.number-1 and pprev.competitorid = p.competitorid );

ALTER TABLE places DROP INDEX CDKTMP;
ALTER TABLE competitors DROP INDEX CDKTMP2;

update sports set name = lower(name);
insert into sports(name, team, customId, nrOfGamePlaces, gameMode ) values ('klaverjassen', false, 16, 4, 2 );
update sports set customId = 15, gameMode = 1, nrOfGamePlaces = 1 where name = 'sjoelen';
insert into sports(name, team, customId, nrOfGamePlaces, gameMode ) values ('badminton dubbel', false, 17, 4, 2 );
insert into sports(name, team, customId, nrOfGamePlaces, gameMode ) values ('squash dubbel', false, 18, 4, 2 );
insert into sports(name, team, customId, nrOfGamePlaces, gameMode ) values ('tennis dubbel', false, 19, 4, 2 );
insert into sports(name, team, customId, nrOfGamePlaces, gameMode ) values ('tafeltennis dubbel', false, 20, 4, 2 );

-- from sports
update sports set gameMode = 2, nrOfGamePlaces = 2 where gameMode = 0;
update planningConfigs set creationStrategy = 1;

-- scoreConfigs: fk to competitionSports needs to be not null again
-- fields: fk to competitionSports needs to be not null again
INSERT INTO competitionSports ( sportId, competitionId )( SELECT sportid, competitionid from sportconfigs );
INSERT INTO gameAmountConfigs ( amount, roundNumberId, competitionSportId )(
    SELECT pc.nrOfHeadtohead, rn.id, (select id from competitionSports where competitionId = rn.competitionId ) from roundNumbers rn join planningConfigs pc on rn.planningConfigId = pc.id
);
-- parent is null
INSERT INTO scoreConfigs ( direction, maximum, enabled, parentId, competitionSportId, roundId )(
    SELECT ssc.direction, ssc.maximum, ssc.enabled, null, (select id from competitionSports where competitionId = rn.competitionId ), r.id from rounds r join roundNumbers rn on r.numberId = rn.id join sportscoreconfigs ssc on ssc.roundnumberid = rn.id and ssc.parentid is null
);
-- parent is not null
INSERT INTO scoreConfigs ( direction, maximum, enabled, parentId, competitionSportId, roundId )(
    SELECT ssc.direction, ssc.maximum, ssc.enabled, sc.id, sc.competitionSportId, sc.roundId
    from scoreConfigs sc join rounds r on r.id = sc.roundId join roundNumbers rn on r.numberId = rn.id join sportscoreconfigs ssc on ssc.roundnumberid = rn.id and ssc.parentid is not null
);
-- CHECK INSERT INTO qualifyAgainstConfigs
INSERT INTO qualifyAgainstConfigs ( winPoints, drawPoints, winPointsExt, drawPointsExt, losePointsExt, pointsCalculation, competitionSportId, roundId )(
    SELECT sc.winPoints, sc.drawPoints, sc.winPointsExt, sc.drawPointsExt, sc.losePointsExt, sc.pointsCalculation, (select id from competitionSports where competitionId = rn.competitionId ), r.id from rounds r join roundNumbers rn on r.numberId = rn.id join sportconfigs sc where sc.competitionid = rn.competitionid and rn.number = 1
);
INSERT INTO gamesAgainst (id, pouleid, resourcebatch, state, startDateTime, refereeId, placerefereeId, fieldId, competitionSportId )
SELECT id, pouleid, resourcebatch, state, startDateTime, refereeId, placerefereeId, fieldId, (select id from competitionSports where competitionId = ( select rn.competitionId from poules p join rounds r on r.id = p.roundid join roundNumbers rn on rn.id = r.numberid where p.id = games.pouleid ) ) from games
;
INSERT INTO gamePlacesAgainst (side, placeId, gameId)
    (
        SELECT if(homeAway,1,2), placeId, gameId from gameplaces
    );
INSERT INTO scoresAgainst (phase, number, home, away, gameId)
    (
        SELECT phase, number, home, away, gameId from gamescores
    );

-- ACC
-- delete from planninginputs where exists ( select * from plannings where timeoutSeconds < 0 and inputId = planninginputs.id );

-- delete from plannings;
