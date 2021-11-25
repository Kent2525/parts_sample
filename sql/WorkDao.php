<?php
//select文
//別名
//配列を上げる。call_user_func_array
//INNER JOIN
//json_decode
//SUM関数
//算術演算子 ✖︎
//GROUP BY
namespace App\Classes\Daos;

use App\Classes\Constants;
use Illuminate\Support\Facades\DB;

// ------------------------------------------------------------------------------ //
class WorkDao implements Constants
{
    // -----------------------------------------------------------------------//
    public function getPartnerAndPlaceById($partner_id)
    {
      $query = <<<SQL
          SELECT --ここで取得するカラムを指定する
            part.place_id --partnersテーブルのplace_id
            ,pl.place_name --placesテーブルのplace_name
            ,part.company_id --partnersテーブルのcompany_id
            ,com.name AS 'company_name' --AS以降の文字列は実際に取得する際のKey名
            ,part.id AS 'partner_id'
          FROM --テーブルを指定
            partners part
          INNER JOIN companies com --テーブルを指定
            ON com.id = part.company_id AND com.deleted = 0 -- 左辺=右辺が一致したレコード及びcompaniesのdeleted =0を取得
          INNER JOIN places pl
            ON pl.id = part.place_id AND pl.deleted = 0
          WHERE
            part.id = ? --リクエストできた値のみを検索
            AND part.deleted = 0 --FROMで書かれたpartnersテーブルの取得条件はここに書く。
            AND part.invite_status = 1
      ;
      SQL;
        $values = [$partner_id];

        $result_set = DB::connection(self::CONN_KEY_WORK)->selectOne($query, $values);
        $result = json_decode(collect($result_set), true);

        return $result;
    }

    // -------------------------------------------------------------------------- //
    public function getSiteAndPlaceById($site_sync)
    {
      $query = <<<SQL
            SELECT
              pl.id AS 'place_id' , 
              pl.place_name ,
              s.id AS 'site_id',
              s.name
            FROM
              sites s
            INNER JOIN places pl
              ON s.place_id = pl.id AND pl.deleted = 0
            WHERE
              s.id = ?
              AND s.deleted = 0
            ;
            SQL;

      $values = [$site_sync];
      $result_set = DB::connection(self::CONN_KEY_WORK)->select($query, $values);
      $result_json = json_decode(collect($result_set), true);
      // 配列が深くなってしまうので階層を上げる。
      $result =call_user_func_array("array_merge", $result_json);

      return $result;
    }
    // -------------------------------------------------------------------------- //
    public function getSitesByPlace($place_id, $site_id = null)
    {
      $query = <<<SQL
          SELECT
            s.place_id
            ,s.id AS 'site_id'
            ,s.name AS 'site_name'
            ,icre.post1
            ,icre.post2
            ,z.zone_title
            ,icre.city
            ,icre.address1
            ,icre.address2
            ,o.name AS 'office_name'
            ,vc.user_name AS 'manager_name'
            ,vc.id AS 'manager_id'
            ,c.site_start_date
            ,c.site_end_date
            ,sum(sf.floor_area) as 'floor_area_total' --SUM関数
            ,sum(sf.floor_area) * 0.7 as 'standard_man_hours'  -- 算術演算子
          FROM
            sites AS s
          INNER JOIN contracts c
            ON c.site_id = s.id
          LEFT JOIN ieleco_customer_real_estates icre
            ON icre.id = s.customer_real_estate_id
          LEFT JOIN zones z
            ON z.id = icre.zone_id
          LEFT JOIN offices o
            ON o.id = c.office_id AND o.deleted = 0
          LEFT JOIN v_crews vc
            ON vc.id = c.admin AND vc.deleted = 0
            AND vc.invite_status = 1
            AND vc.employee_invite_status = 1
            AND vc.partner_invite_status = 1
          LEFT JOIN site_floors sf
            ON sf.site_id = s.id AND sf.deleted = 0
          WHERE
            s.place_id = ? -- ?はリクエストからきた変数の中身を入れる。
          GROUP BY -- SUM関数使う場合は必須。エラーが出る場合はconfig/database.phpのstrictをtrueからfalseに変える必要がある。
            s.id
          SQL;

        $values = [$place_id];

        if ($site_id != null) {
            $query .= PHP_EOL;
            $query .= <<<SQL
                AND s.id = ?
                SQL;

            $values[] = $site_id;
        }

        $query .= PHP_EOL;
        $query .= <<<SQL
              AND s.deleted = 0
            ORDER BY
              s.id
            ;
            SQL;

        $result_set = DB::connection(self::CONN_KEY_WORK)->select($query, $values);
        $result_json = json_decode(collect($result_set), true);
        $result = [$result_json[1]];
   
        return $result;
    }
  }