window.AdminPortalStorage = (() => {
  const ACTIVITY_HISTORY_LIMIT = 100;

  function getActivityStorageKey(email) {
    const account = String(email || '').trim().toLowerCase();
    if (!account) throw new Error('An account email is required to access activity history.');
    return `law-office-activity-history:${account}`;
  }

  function readActivityHistory(email) {
    const savedActivity = window.localStorage.getItem(getActivityStorageKey(email));
    if (!savedActivity) return [];

    let activities;
    try {
      activities = JSON.parse(savedActivity);
    } catch (error) {
      console.error('Unable to parse account activity history from browser storage.', error);
      throw error;
    }

    if (!Array.isArray(activities) || activities.some((activity) => (
      !activity
      || typeof activity.activity_id !== 'string'
      || typeof activity.date !== 'string'
      || typeof activity.activity !== 'string'
      || typeof activity.record !== 'string'
      || typeof activity.performed_by !== 'string'
      || typeof activity.status !== 'string'
    ))) {
      const error = new Error('Account activity history has an unrecognized format.');
      console.error(error.message);
      throw error;
    }

    return activities.slice(0, ACTIVITY_HISTORY_LIMIT);
  }

  function recordActivity(email, activity, record, status, performedBy) {
    const activityText = String(activity || '').trim();
    if (!activityText) throw new Error('An activity description is required.');

    const currentActivity = readActivityHistory(email);
    const entry = {
      activity_id: `ACT-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
      date: new Date().toISOString(),
      activity: activityText.slice(0, 200),
      record: String(record || 'Attorney workspace').slice(0, 200),
      performed_by: String(performedBy || 'Account user').slice(0, 100),
      status: String(status || 'Completed').slice(0, 50)
    };

    window.localStorage.setItem(
      getActivityStorageKey(email),
      JSON.stringify([entry, ...currentActivity].slice(0, ACTIVITY_HISTORY_LIMIT))
    );
    return entry;
  }
  function getLocalDateString(date = new Date()) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  }

  return {
    readActivityHistory,
    recordActivity,
    getLocalDateString
  };
})();
