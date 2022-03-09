-- PRE PRE PRE doctrine-update =============================================================


-- POST POST POST doctrine-update ===========================================================

--  php bin/console.php app:create-planning 61533 --loglevel=200

-- set input MinNrOfBatches
# update planningInputs set recreatedAt = null;
# update planningInputs set seekingPercentage = -1;
# update planningInputs set minNrOfBatches = (select min(nrOfBatches) from plannings p where p.inputId = planningInputs.id and p.state = 2);
