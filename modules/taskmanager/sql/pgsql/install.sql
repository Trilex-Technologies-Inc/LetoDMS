CREATE TABLE IF NOT EXISTS tblModuleTasks (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  due_date VARCHAR(10),
  completed SMALLINT NOT NULL DEFAULT 0,
  created_at INTEGER NOT NULL
)
