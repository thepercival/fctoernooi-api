-- PRE PRE PRE doctrine-update =============================================================
update gameAmountConfigs set amount = nrOfGamesPerPlaceMixed where amount = 0;

-- POST POST POST doctrine-update ===========================================================

--  php bin/console.php app:create-planning 61533 --loglevel=200

-- set input MinNrOfBatches
# update planningInputs
# set recreatedAt = null;
# update planningInputs
# set seekingPercentage = -1;
# update planningInputs
# set minNrOfBatches = (select min(nrOfBatches) from plannings p where p.inputId = planningInputs.id and p.state = 2);

-- multisports which needs to migrate
select 	*
from 	competitions
where 	(select count(*) from competitionSports where competitionId = competitions.id) > 1
and		exists(select * from competitionSports where competitionId = competitions.id and nrOfH2H > 0)




