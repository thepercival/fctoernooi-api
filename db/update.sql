-- PRE PRE PRE doctrine-update =============================================================


-- POST POST POST doctrine-update ===========================================================

select *,
       coalesce((select max(batchNr) from planningGamesAgainst pga where pga.planningId = p.id), 0) +
       coalesce((select max(batchNr) from planningGamesTogether pgt where pgt.planningId = p.id),
                0) as maxBatchNrByGames
from plannings p
where p.state = 2
  and coalesce((select max(batchNr) from planningGamesAgainst pga where pga.planningId = p.id), 0) +
      coalesce((select max(batchNr) from planningGamesTogether pgt where pgt.planningId = p.id), 0) <> p.nrOfBatches
          - -redo
    php bin/console.php app:create-planning 61533 --loglevel=200


-- set input MinNrOfBatches
update planningInputs
set minNrOfBatches = (select min(nrOfBatches) from plannings p where p.inputId = planningInputs.id and p.state = 2)
-- where 	(select count(*) from plannings where inputId = planningInputs.id and plannings.state = 2) > 1
-- where	id = 60395 -- (29,30,35)



