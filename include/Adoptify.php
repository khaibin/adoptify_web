<?php

class Adoptify
{
    private $con;
    private const IMAGE_PATH_PET_DOG = 'uploads/pet/dog/';

    public function __construct(mysqli $con)
    {
        $this->con = $con;
    }


    public function login($email, $password)
    {
        $query = "
          SELECT password
          FROM user
          WHERE email = ? AND is_disabled = 0
        ";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return ($user && password_verify($password, $user['password']));
    }


    public function verifyAccessToken($user_id, $access_token)
    {
        $query = "
          SELECT MD5(CONCAT(id, password, fcm_token)) AS access_token
          FROM user
          WHERE id = ? AND is_disabled = 0
        ";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $user && ($access_token == $user['access_token']);
    }


    public function getUserDetails($user_id)
    {
        $query = "
          SELECT id, name, email, country_code, created_at
          FROM user
          WHERE id = ?
        ";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $user;
    }


    public function addUser($name, $email, $password, $country_code, $fcm_token)
    {
        $name = trim($name);
        $password = password_hash($password, PASSWORD_DEFAULT);
        $country_code = strtoupper($country_code);

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $query = "
              INSERT INTO user (name, email, password, country_code, fcm_token)
              VALUES (?, ?, ?, ?, ?)
            ";
            $stmt = $this->con->prepare($query);
            $stmt->bind_param('sssss', $name, $email, $password, $country_code, $fcm_token);
            $stmt->execute();
            $stmt->store_result();
            $user_id = $stmt->insert_id;
            $stmt->close();

            return $user_id;
        }

        return 0;
    }


    public function updateUserDetails($user_id, $name, $email, $country_code)
    {
        $name = trim($name);
        $country_code = strtoupper($country_code);

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $query = "
              UPDATE user
              SET name = ?, email = ?, country_code = ?
              WHERE id = ?
            ";
            $stmt = $this->con->prepare($query);
            $stmt->bind_param('sssi', $name, $email, $country_code, $user_id);
            $result = $stmt->execute();
            $stmt->close();

            return $result;
        }

        return false;
    }


    public function updateUserPassword($user_id, $new_password)
    {
        $new_password = password_hash($new_password, PASSWORD_DEFAULT);

        $query = "
          UPDATE user
          SET password = ?
          WHERE id = ?
        ";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('si', $new_password, $user_id);
        $stmt->execute();
        $stmt->store_result();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();

        return $affected_rows > 0;
    }


    public function updateUserFcmToken($user_id, $fcm_token)
    {
        $query = "
          UPDATE user
          SET fcm_token = ?
          WHERE id = ?
        ";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('si', $fcm_token, $user_id);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }


    public function disableUser($user_id)
    {
        $query = "
          UPDATE user
          SET is_disabled = 1
          WHERE id = ?
        ";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->store_result();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();

        return $affected_rows > 0;
    }


    public function getUserId($email)
    {
        $query = "
          SELECT id
          FROM user
          WHERE email = ?
        ";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user_id = $stmt->get_result()->fetch_assoc()['id'];
        $stmt->close();

        return $user_id;
    }


    public function isEmailExists($email, $user_id = 0)
    {
        if ($user_id && !$this->getUserDetails($user_id)) {
            $user_id = 0;
        }

        if ($user_id) {
            $query = "
              SELECT COUNT(*) AS count
              FROM user
              WHERE email = ? AND email != (
                SELECT email
                FROM user
                WHERE id = ?
              )
            ";
            $stmt = $this->con->prepare($query);
            $stmt->bind_param('si', $email, $user_id);

        } else {
            $query = "
              SELECT COUNT(*) AS count
              FROM user
              WHERE email = ?
            ";
            $stmt = $this->con->prepare($query);
            $stmt->bind_param('s', $email);
        }

        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();

        return $count > 0;
    }


    public function getAccessToken($user_id)
    {
        $query = "
          SELECT MD5(CONCAT(id, password, fcm_token)) AS access_token
          FROM user
          WHERE id = ?
        ";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $user ? $user['access_token'] : null;
    }


    public function verifyPassword($user_id, $password)
    {
        $query = "
          SELECT password
          FROM user
          WHERE id = ?
        ";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return password_verify($password, $user['password']);
    }



    public function getDog($dog_id)
    {
        $query = "
          SELECT d.id, d.user_id, u.name AS user_name, d.breed, d.gender,
            (((YEAR(NOW()) * 12) + MONTH(NOW())) - ((YEAR(d.dob) * 12) + MONTH(d.dob))) AS age_month, d.description,
            d.country_code, d.contact_name, d.contact_phone, d.contact_latitude, d.contact_longitude,
            d.contact_area_level_1, d.contact_area_level_2, d.view_count, d.created_at,
            DATEDIFF(d.expiry_date, DATE(NOW())) AS day_left
          FROM dog AS d
          INNER JOIN user AS u ON d.user_id = u.id
          WHERE d.id = ? AND DATEDIFF(d.expiry_date, DATE(NOW())) > 0 AND d.is_deleted = 0
        ";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('i', $dog_id);
        $stmt->execute();
        $dog_temp = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($dog_temp) {

            $query = "
              SELECT path
              FROM dog_image
              WHERE dog_id = ?
            ";
            $stmt = $this->con->prepare($query);
            $stmt->bind_param('i', $dog_id);
            $stmt->execute();
            $images_temp = $stmt->get_result();
            $stmt->close();

            $images = [];

            while ($image = $images_temp->fetch_assoc()) {
                array_push($images, $image['path']);
            }

            $query = "
              SELECT dc.id AS dog_comment_id, dc.user_id, u.name AS user_name, dc.content, dc.created_at
              FROM dog_comment AS dc
              INNER JOIN user AS u ON dc.user_id = u.id
              WHERE dc.dog_id = ? AND dc.is_deleted = 0
            ";
            $stmt = $this->con->prepare($query);
            $stmt->bind_param('i', $dog_id);
            $stmt->execute();
            $comments_temp = $stmt->get_result();
            $stmt->close();

            $comments = [];

            while ($comment = $comments_temp->fetch_assoc()) {
                array_push($comments, [
                    'dog_comment_id' => $comment['dog_comment_id'],
                    'user' => [
                        'user_id' => $comment['user_id'],
                        'name' => $comment['user_name']
                    ],
                    'content' => $comment['content'],
                    'created_at' => $comment['created_at']
                ]);
            }

            return [
                'dog_id' => $dog_temp['id'],
                'user' => [
                    'user_id' => $dog_temp['user_id'],
                    'name' => $dog_temp['user_name']
                ],
                'breed' => $dog_temp['breed'],
                'gender' => $dog_temp['gender'],
                'age_month' => $dog_temp['age_month'],
                'images' => $images,
                'description' => $dog_temp['description'],
                'country_code' => $dog_temp['country_code'],
                'contact' => [
                    'name' => $dog_temp['contact_name'],
                    'phone' => $dog_temp['contact_phone'],
                    'latitude' => $dog_temp['contact_latitude'],
                    'longitude' => $dog_temp['contact_longitude'],
                    'area_level_1' => $dog_temp['contact_area_level_1'],
                    'area_level_2' => $dog_temp['contact_area_level_2']
                ],
                'comments' => $comments,
                'view_count' => $dog_temp['view_count'],
                'day_left' => $dog_temp['day_left'],
                'created_at' => $dog_temp['created_at']
            ];
        }

        return null;
    }


    public function addDog($user_id, $breed, $gender, $birth_year, $birth_month, $description, $contact_name,
        $contact_phone, $contact_place_id)
    {
        $gender = strtoupper($gender);
        $user = $this->getUserDetails($user_id);
        $user_country_code = $user['country_code'];
        $dob = $birth_year . '-' . $birth_month . '-' . '01';

        $url = 'https://maps.googleapis.com/maps/api/place/details/json?placeid=' . $contact_place_id .
            '&key=AIzaSyC80DoVueEYQV2-c7Wo0NRtc4fuGDOo-5g';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if ($response['status'] == 'OK') {

            $address_component_size = sizeof($response['result']['address_components']);
            $country_code = $response['result']['address_components'][$address_component_size - 2]['short_name'];
            $area_level_1 = $response['result']['address_components'][$address_component_size - 3]['long_name'];
            $area_level_2 = $response['result']['address_components'][$address_component_size - 4]['long_name'];
            $latitude = $response['result']['geometry']['location']['lat'];
            $longitude = $response['result']['geometry']['location']['lng'];

            if ($country_code == $user_country_code) {

                $query = "
                  INSERT INTO dog (user_id, breed, gender, dob, description, country_code, contact_name, contact_phone,
                    contact_latitude, contact_longitude, contact_area_level_1, contact_area_level_2, expiry_date)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(DATE(NOW()), INTERVAL 150 DAY)) 
                ";
                $stmt = $this->con->prepare($query);
                $stmt->bind_param('isssssssddss', $user_id, $breed, $gender, $dob, $description,
                    $country_code, $contact_name, $contact_phone, $latitude, $longitude, $area_level_1, $area_level_2);
                $stmt->execute();
                $dog_id = $stmt->insert_id;
                $affected_rows = $stmt->affected_rows;
                $stmt->close();

                if ($affected_rows > 0) {
                    return $dog_id;
                }
            }
        }

        return 0;
    }


    public function updateDogImages($dog_id, $images)
    {
        require __DIR__ . '/../include/ImageResizer.php';

        array_map('unlink', glob($dog_id . '-*.*'));

        $query = "
          DELETE FROM dog_image
          WHERE dog_id = ?
        ";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('i', $dog_id);
        $result = $stmt->execute();
        $stmt->close();

        if ($result) {

            foreach ($images as $image) {

                $name = $dog_id . '-' . md5(openssl_random_pseudo_bytes(32)) . '.jpg';
                $target = self::IMAGE_PATH_PET_DOG . $name;

                $imageResizer = new ImageResizer($image['tmp_name'], __DIR__ . '/../' . $target);
                echo __DIR__ . '/../' . $target;

                if ($imageResizer->resize(450, 600)) {
                    $query = "
                      INSERT INTO dog_image (dog_id, path)
                      VALUES (?, ?)
                    ";
                    $stmt = $this->con->prepare($query);
                    $stmt->bind_param('is', $dog_id, $target);
                    $stmt->execute();
                    $affected_rows = $stmt->affected_rows;
                    $stmt->close();

                    if ($affected_rows < 1) {
                        return false;
                    }

                } else {
                    return false;
                }

            }

            return true;
        }

        return false;
    }


    public function updateDogDetails($dog_id, $breed, $gender, $birth_year, $birth_month, $description, $contact_name, $contact_phone)
    {
        $gender = strtoupper($gender);
        $dob = $birth_year . '-' . $birth_month . '-' . '01';

        $query = "
          UPDATE dog
          SET breed = ?, gender = ?, dob = ?, description = ?, contact_name = ?, contact_phone = ?
          WHERE id = ?
        ";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('ssssssi', $breed, $gender, $dob, $description, $contact_name, $contact_phone, $dog_id);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }


    public function updateDogContactPlace($dog_id, $contact_place_id)
    {
        $dog = $this->getDog($dog_id);

        $url = 'https://maps.googleapis.com/maps/api/place/details/json?placeid=' . $contact_place_id .
            '&key=AIzaSyC80DoVueEYQV2-c7Wo0NRtc4fuGDOo-5g';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        if ($response['status'] == 'OK') {

            $address_component_size = sizeof($response['result']['address_components']);
            $country_code = $response['result']['address_components'][$address_component_size - 2]['short_name'];
            $area_level_1 = $response['result']['address_components'][$address_component_size - 3]['long_name'];
            $area_level_2 = $response['result']['address_components'][$address_component_size - 4]['long_name'];
            $latitude = $response['result']['geometry']['location']['lat'];
            $longitude = $response['result']['geometry']['location']['lng'];

            if ($country_code == $dog['country_code']) {

                $query = "
                  UPDATE dog
                  SET contact_latitude = ?, contact_longitude = ?, contact_area_level_1 = ?, contact_area_level_2 = ?
                  WHERE id = ?
                ";
                $stmt = $this->con->prepare($query);
                $stmt->bind_param('ddssi', $latitude, $longitude, $area_level_1, $area_level_2, $dog_id);
                $result = $stmt->execute();
                $stmt->close();

                return $result;
            }
        }

        return false;
    }


    public function updateDogIncrementViews($dog_id) {

        $query = "
          UPDATE dog
          SET view_count = view_count + 1
          WHERE id = ?
        ";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('i', $dog_id);
        $stmt->execute();
        $stmt->store_result();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();

        return $affected_rows > 0;
    }


    public function deleteDog($dog_id) {

        $query = "
          UPDATE dog
          SET is_deleted = 1
          WHERE id = ?
        ";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('i', $dog_id);
        $stmt->execute();
        $stmt->store_result();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();

        return $affected_rows > 0;
    }


    public function commentDog($dog_id, $user_id, $content)
    {
        $query = "
          INSERT INTO dog_comment (user_id, dog_id, content)
          VALUES (?, ?, ?)
        ";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('iis', $user_id, $dog_id, $content);
        $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $dog_comment_id = $stmt->insert_id;
        $stmt->close();

        if ($affected_rows > 0) {
            return $dog_comment_id;
        }

        return 0;
    }


    public function reportDog($user_id, $dog_id) {

        $query = "
          INSERT INTO dog_report (dog_id, user_id)
          VALUES (?, ?)
        ";
        $stmt = $this->con->prepare($query);
        $stmt->bind_param('ii', $user_id, $dog_id);
        $stmt->execute();
        $stmt->store_result();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();

        return $affected_rows > 0;
    }


    public function reArrayImages($images) {
        $new = [];
        foreach ($images as $key => $all) {
            foreach ($all as $i => $val) {
                $new[$i][$key] = $val;
                if ($key == 'name') {
                    $new[$i]['extension'] = strtolower(pathinfo($val,PATHINFO_EXTENSION));
                }
            }
        }
        return $new;
    }


}