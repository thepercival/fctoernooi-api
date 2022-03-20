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

insert into creditActions(userId, action, nrOfCredits, atDateTime) (select id, 'CreateAccountReward', 3, CURRENT_TIMESTAMP from users);

delete
from plannings
where id in (
    select p.id
    from plannings p
    where p.maxNrOfBatchGames < (select max(psub.minNrOfBatchGames)
                                 from plannings psub
                                 where psub.inputId = p.inputId
                                   and state = 2
                                   and psub.minNrOfBatchGames = psub.maxNrOfBatchGames)
       or (
                p.maxNrOfBatchGames = (select max(psub.minNrOfBatchGames)
                                       from plannings psub
                                       where psub.inputId = p.inputId
                                         and state = 2
                                         and psub.minNrOfBatchGames = psub.maxNrOfBatchGames)
            and
                p.minNrOfBatchGames < (select max(psub.minNrOfBatchGames)
                                       from plannings psub
                                       where psub.inputId = p.inputId
                                         and state = 2
                                         and psub.minNrOfBatchGames = psub.maxNrOfBatchGames)
        )
);


-- set input MinNrOfBatches
# update planningInputs set recreatedAt = null;
# update planningInputs set seekingPercentage = -1;
# update planningInputs set minNrOfBatches = (select min(nrOfBatches) from plannings p where p.inputId = planningInputs.id and p.state = 2);
