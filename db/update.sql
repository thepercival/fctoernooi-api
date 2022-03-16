-- PRE PRE PRE doctrine-update =============================================================
ALTER TABLE tournaments
    CHANGE competitionid competitionId INT NOT NULL;
ALTER TABLE tournamentUsers
    CHANGE userid userId INT NOT NULL;

-- POST POST POST doctrine-update ===========================================================
insert into recesses(tournamentId, startDateTime, endDateTime) (select id, breakStartDateTime, breakEndDateTime
                                                                from tournaments
                                                                where breakStartDateTime is not null
                                                                  and breakEndDateTime is not null);

update users
set validateIn  = 3,
    nrOfCredits = 3;

--  php bin/console.php app:create-planning 61533 --loglevel=200

-- set input MinNrOfBatches
# update planningInputs set recreatedAt = null;
# update planningInputs set seekingPercentage = -1;
# update planningInputs set minNrOfBatches = (select min(nrOfBatches) from plannings p where p.inputId = planningInputs.id and p.state = 2);
