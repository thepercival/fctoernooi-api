-- PRE PRE PRE doctrine-update =============================================================
insert into tournamentUsers(tournamentId, userId, roles) (select tournamentId, 4005, roles
                                                          from tournamentUsers
                                                          where userId = 3816)
delete
from users
where id = 3816;

delete
from users
where id = 5955

-- POST POST POST doctrine-update ===========================================================

-- insert into creditActions(userId, action, nrOfCredits, atDateTime) (select id, 'CreateAccountReward', 3, CURRENT_TIMESTAMP from users);

-- set input MinNrOfBatches
# update planningInputs set recreatedAt = null;
# update planningInputs set seekingPercentage = -1;
# update planningInputs set minNrOfBatches = (select min(nrOfBatches) from plannings p where p.inputId = planningInputs.id and p.state = 2);



