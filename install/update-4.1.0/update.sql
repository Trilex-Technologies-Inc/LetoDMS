CREATE TABLE `tblRoles` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(80) NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`id`), UNIQUE (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE `tblPermissions` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  PRIMARY KEY (`id`), UNIQUE (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE `tblRolePermissions` (
  `roleID` int(11) NOT NULL, `permissionID` int(11) NOT NULL,
  PRIMARY KEY (`roleID`, `permissionID`),
  CONSTRAINT `tblRolePermissions_role` FOREIGN KEY (`roleID`) REFERENCES `tblRoles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tblRolePermissions_permission` FOREIGN KEY (`permissionID`) REFERENCES `tblPermissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
CREATE TABLE `tblUserRoles` (
  `userID` int(11) NOT NULL, `roleID` int(11) NOT NULL,
  PRIMARY KEY (`userID`, `roleID`),
  CONSTRAINT `tblUserRoles_user` FOREIGN KEY (`userID`) REFERENCES `tblUsers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tblUserRoles_role` FOREIGN KEY (`roleID`) REFERENCES `tblRoles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
INSERT INTO `tblPermissions` (`name`, `description`) VALUES
('role.manage', 'Create roles and assign permissions and users'),
('user.manage', 'Create, edit, disable, and delete users'),
('group.manage', 'Create groups and manage membership'),
('document.create', 'Create documents where the object ACL permits writing'),
('document.update', 'Update documents where the object ACL permits writing'),
('document.delete', 'Delete documents where the object ACL permits full access'),
('workflow.manage', 'Manage workflow definitions'),
('workflow.review', 'Review documents assigned through a workflow'),
('workflow.approve', 'Approve documents assigned through a workflow'),
('navigation.content', 'Show the Content navigation item'),
('navigation.my_documents', 'Show the My Documents navigation item'),
('navigation.calendar', 'Show the Calendar navigation item'),
('navigation.help', 'Show the Help navigation item');
