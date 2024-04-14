DROP TABLE planningScheduleGamePlaces;

DROP TABLE planningScheduleGames;

DROP TABLE planningSportSchedules ;

DROP TABLE planningSchedules;

DROP TABLE planningGamePlacesAgainst  ;

DROP TABLE planningGamesAgainst ;

DROP TABLE planningGamePlacesTogether ;

DROP TABLE planningGamesTogether ;

DROP TABLE plannings ;

DROP TABLE planningReferees;

DROP TABLE planningFields ;

DROP TABLE planningPlaces ;

DROP TABLE planningPoules ;

DROP TABLE planningSports ;

DROP TABLE planningInputs;


-- fctoernooi
-- select distinct defaultGameMode from sports;
alter table sports modify column defaultGameMode varchar(12) not null;
update sports set defaultGameMode = 'single' where defaultGameMode = '1';
update sports set defaultGameMode = 'against' where defaultGameMode = '2';
update sports set defaultGameMode = 'allInOneGame' where defaultGameMode = '3';
-- select distinct defaultGameMode from sports;

-- select distinct gameMode from competitionSports;
alter table competitionSports modify column gameMode varchar(12) not null;
update competitionSports set gameMode = 'single' where gameMode = '1';
update competitionSports set gameMode = 'against' where gameMode = '2';
update competitionSports set gameMode = 'allInOneGame' where gameMode = '3';
-- select distinct gameMode from competitionSports;

-- select distinct state from againstGames ag;
alter table againstGames modify column state varchar(10) not null;
update againstGames set state = 'created' where state = '1';
update againstGames set state = 'inProgress' where state = '2';
update againstGames set state = 'finished' where state = '4';
update againstGames set state = 'canceled' where state = '8';
-- select distinct state from againstGames ag;

-- select distinct state from togetherGames tg;
alter table togetherGames modify column state varchar(10) not null;
update togetherGames set state = 'created' where state = '1';
update togetherGames set state = 'inProgress' where state = '2';
update togetherGames set state = 'finished' where state = '4';
update togetherGames set state = 'canceled' where state = '8';
-- select distinct state from togetherGames;

-- select distinct side from againstGamePlaces;
alter table againstGamePlaces modify column side varchar(4) not null;
update againstGamePlaces set side = 'home' where side  = '1';
update againstGamePlaces set side  = 'away' where side  = '2';
-- select distinct side from againstGamePlaces;

-- select distinct selfReferee from planningConfigs;
alter table planningConfigs modify column selfReferee varchar(11) not null;
update planningConfigs set selfReferee = 'disabled' where selfReferee  = '0';
update planningConfigs set selfReferee = 'otherPoules' where selfReferee  = '1';
update planningConfigs set selfReferee  = 'samePoule' where selfReferee  = '2';
-- select distinct selfReferee from planningConfigs;

-- select distinct editMode from planningConfigs;
alter table planningConfigs modify column editMode varchar(6) not null;
update planningConfigs set editMode = 'auto' where editMode  = '1';
update planningConfigs set editMode = 'manual' where editMode  = '2';
-- select distinct editMode from planningConfigs;

-- select distinct distribution from qualifyGroups;
alter table qualifyGroups modify column distribution varchar(15) not null;
update qualifyGroups set distribution = 'horizontalSnake' where distribution  = '0';
update qualifyGroups set distribution = 'vertical' where distribution  = '1';
-- select distinct distribution from qualifyGroups;

-- deze via doctrine proberen met length ingevuld
-- select distinct againstRuleSet from competitions;
alter table competitions modify column againstRuleSet varchar(10) not null;
update competitions set againstRuleSet = 'diffFirst' where againstRuleSet  = '1';
update competitions set againstRuleSet = 'amongFirst' where againstRuleSet  = '2';
-- select distinct againstRuleSet from competitions;

-- select distinct defaultPointsCalculation from competitionSports;
alter table competitionSports modify column defaultPointsCalculation varchar(17) not null;
update competitionSports set defaultPointsCalculation = 'againstGamePoints' where defaultPointsCalculation  = '0';
update competitionSports set defaultPointsCalculation = 'scores' where defaultPointsCalculation  = '1';
update competitionSports set defaultPointsCalculation = 'both' where defaultPointsCalculation  = '2';
-- select distinct defaultPointsCalculation from competitionSports;

-- select distinct pointsCalculation from againstQualifyConfigs;
alter table againstQualifyConfigs modify column pointsCalculation varchar(17) not null;
update againstQualifyConfigs set pointsCalculation = 'againstGamePoints' where pointsCalculation  = '0';
update againstQualifyConfigs set pointsCalculation = 'scores' where pointsCalculation  = '1';
update againstQualifyConfigs set pointsCalculation = 'both' where pointsCalculation  = '2';
-- select distinct pointsCalculation from againstQualifyConfigs;


update tournaments set intro = 'Dit is een standaard welkomsbericht. Je kunt het welkomstbericht aanpassen, de huisregels opgeven, een locatie opgeven en sponsoren tonen.' where intro = '' and public = 1;

-- two same sports, remove
-- select 	*
-- from 	competitionSports cs
-- where 	exists (
-- 			select 	*
-- 			from 	competitionSports csSub
-- 			where 	csSub.id <> cs.id and csSub.sportId = cs.sportId and csSub.competitionId = cs.competitionId
-- 			and 	csSub.gameMode = cs.gameMode and cs.nrOfHomePlaces = csSub.nrOfHomePlaces and cs.nrOfAwayPlaces = csSub.nrOfAwayPlaces
-- 			and 	cs.nrOfGamePlaces = csSub.nrOfGamePlaces
-- 		);
--
-- delete from competitions where id in (15612, 20797);

-- select 	r.*
-- from 	referees r
-- where 	( exists (
-- 			select 	*
-- 			from 	referees rSub
-- 			where 	rSub.id <> r.id and rSub.competitionId = r.competitionId
-- 			and 	rSub.priority = r.priority
-- 		) )
-- and 		( not exists (
-- 			select 	*
-- 			from 	referees rSub
-- 			where 	rSub.competitionId = r.competitionId
-- 			and 	rSub.priority = (r.priority - 1) and r.priority > 1
-- 		) )
-- order by r.competitionId, r.priority
