UPDATE subscriptions
SET status = 'trial'
WHERE plan = 'trial' AND status = 'active';
