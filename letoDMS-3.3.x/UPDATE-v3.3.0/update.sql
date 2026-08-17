UPDATE tblVersion set major=3, minor=3, subminor=0;
ALTER TABLE tblDocumentContent MODIFY mimeType varchar(100);
ALTER TABLE tblDocumentFiles MODIFY mimeType varchar(100);
ALTER TABLE tblFolders ADD COLUMN `folderList` text NOT NULL;
