-- PRE PRE PRE doctrine-update =============================================================
-- DO SQL WITH HAND
-- ALTER TABLE rounds CHANGE numberid structureCellId INT NOT NULL;
-- TO
-- ALTER TABLE rounds ADD structureCellId INT  NULL;

-- POST POST POST doctrine-update ===========================================================
update tournaments set intro = 'Welkom bij ons toernooi! Hieronder staan de regels. De onderwerpen kun je met het menu, onderaan het scherm, opvragen.';

-- update planningInputs set uniqueString = REPLACE (uniqueString, ':OP', ':OP(1)') where uniqueString like '%:OP%';
-- update planningInputs set uniqueString = REPLACE (uniqueString, ':SP', ':SP(1)') where uniqueString like '%:SP%';
-- update planningInputs set nrOfSimSelfRefs = 0;
-- update planningInputs set nrOfSimSelfRefs = 1 where uniqueString like '%:OP(1)%' or uniqueString like '%:SP(1)%';
-- update planningConfigs set nrOfSimSelfRefs = 0;
-- update planningConfigs set nrOfSimSelfRefs = 1 where selfReferee > 0;

-- insert into creditActions(userId, action, nrOfCredits, atDateTime) (select id, 'CreateAccountReward', 3, CURRENT_TIMESTAMP from users);

-- set input MinNrOfBatches
# update planningInputs set recreatedAt = null;
# update planningInputs set seekingPercentage = -1;
# update planningInputs set minNrOfBatches = (select min(nrOfBatches) from plannings p where p.inputId = planningInputs.id and p.state = 2);

# STEP 1 update planningInputs set recreatedAt = null, seekingPercentage = -1, minNrOfBatches = (select min(nrOfBatches) from plannings p where p.inputId = planningInputs.id and p.state = 2) where id = 62099;
# STEP 2 php bin/console.php app:recalculate-planning-inputs --id-range=62099-62099
# STEP 3 php bin/console.php app:show-planning 62099 --loglevel=200
# STEP 4 php bin/console.php app:retry-timeout-planning 62099 --maxNrOfGamesInARow=1 --batchGamesRange=3-3 --planningType=GamesInARow --timeoutState=Time1xNoSort --loglevel=200



