<?php

namespace app\data\service;

use think\admin\Service;
use think\Exception;

/**
 * 用户奖励配置
 * Class PrizeService
 * @package app\data\service
 */
class PrizeService extends Service
{
    const PRIZE_01 = 'prize_01';
    const PRIZE_02 = 'prize_02';
    const PRIZE_03 = 'prize_03';
    const PRIZE_04 = 'prize_04';
    const PRIZE_05 = 'prize_05';

    const PRIZES = [
        self::PRIZE_01 => ['code' => self::PRIZE_01, 'name' => '首推奖励', 'func' => '_prize01'],
        self::PRIZE_02 => ['code' => self::PRIZE_02, 'name' => '复购奖励', 'func' => '_prize02'],
        self::PRIZE_03 => ['code' => self::PRIZE_03, 'name' => '直属团队', 'func' => '_prize03'],
        self::PRIZE_04 => ['code' => self::PRIZE_04, 'name' => '间接团队', 'func' => '_prize04'],
        self::PRIZE_05 => ['code' => self::PRIZE_05, 'name' => '差额奖励', 'func' => '_prize05'],
    ];

    protected $user;
    protected $order;
    protected $fromer;

    /**
     * 获取奖励名称
     * @param string $prize
     * @return string
     */
    public function name(string $prize): string
    {
        return self::PRIZES[$prize]['name'] ?? $prize;
    }

    /**
     * 执行订单返利
     * @param string $orderNo
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function execute(string $orderNo)
    {
        // 获取订单数据
        $map = ['order_no' => $orderNo, 'payment_status' => 1];
        $this->order = $this->app->db->name('ShopOrder')->where($map)->find();
        if (empty($this->order)) throw new Exception('订单不存在');
        // 获取用户数据
        $map = ['id' => $this->order['uid']];
        $this->user = $this->app->db->name('DataUser')->where($map)->find();
        if (empty($this->user)) throw new Exception('用户不存在');
        // 获取推荐用户
        if ($this->order['from'] > 0) {
            $map = ['id' => $this->order['from']];
            $this->fromer = $this->app->db->name('DataUser')->where($map)->find();
            if (empty($this->fromer)) throw new Exception('推荐不存在');
        }
        // 批量发放奖励
        foreach (self::PRIZES as $vo) {
            if (method_exists($this, $vo['func'])) {
                $this->{$vo['func']}();
            }
        }
    }

    /**
     * 首推奖励
     * @return boolean
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function _prize01(): bool
    {
        if (empty($this->fromer)) return false;
        $map = ['order_uid' => $this->user['id']];
        if ($this->app->db->name('DataUserRebate')->where($map)->count() > 0) return false;
        if (!$this->checkLevelPrize(PrizeService::PRIZE_01, $this->fromer['vip_number'])) return false;
        // 创建返利奖励记录
        $map = ['type' => PrizeService::PRIZE_01, 'order_no' => $this->order['order_no'], 'order_uid' => $this->order['uid']];
        if ($this->app->db->name('DataUserRebate')->where($map)->count() < 1) {
            if (sysconf('shop.fristType') == 1) {
                $amount = sysconf('shop.fristValue') ?: '0.00';
                $name = PrizeService::instance()->name(PrizeService::PRIZE_01) . "，每人 {$amount} 元";
            } else {
                $amount = sysconf('shop.fristValue') * $this->order['amount_total'] / 100;
                $name = PrizeService::instance()->name(PrizeService::PRIZE_01) . "，订单 " . sysconf('shop.fristValue') . '%';
            }
            $this->app->db->name('DataUserRebate')->insert(array_merge($map, [
                'uid' => $this->fromer['id'], 'name' => $name, 'amount' => $amount, 'order_amount' => $this->order['amount_total'],
            ]));
            // 更新会员奖利金额
            UserService::instance()->syncLevel($this->fromer['id']);
        }
        return true;
    }

    /**
     * 复购奖励
     * @return boolean
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function _prize02(): bool
    {
        if (empty($this->fromer)) return false;
        $map = ['order_uid' => $this->user['id']];
        if ($this->app->db->name('DataUserRebate')->where($map)->count() < 1) return false;
        if (!$this->checkLevelPrize(PrizeService::PRIZE_02, $this->fromer['vip_number'])) return false;
        // 创建返利奖励记录
        $map = ['type' => PrizeService::PRIZE_02, 'order_no' => $this->order['order_no'], 'order_uid' => $this->order['uid']];
        if ($this->app->db->name('DataUserRebate')->where($map)->count() < 1) {
            if (sysconf('shop.repeatType') == 1) {
                $amount = sysconf('shop.repeatValue') ?: '0.00';
                $name = PrizeService::instance()->name(PrizeService::PRIZE_02) . "，每人 {$amount} 元";
            } else {
                $amount = sysconf('shop.repeatValue') * $this->order['amount_total'] / 100;
                $name = PrizeService::instance()->name(PrizeService::PRIZE_02) . "，订单 " . sysconf('shop.repeatValue') . '%';
            }
            $this->app->db->name('DataUserRebate')->insert(array_merge($map, [
                'uid' => $this->fromer['id'], 'name' => $name, 'amount' => $amount, 'order_amount' => $this->order['amount_total'],
            ]));
            // 更新会员奖利金额
            UserService::instance()->syncLevel($this->fromer['id']);
        }
        return true;
    }

    /**
     * 直属团队
     * @return boolean
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function _prize03(): bool
    {
        if (empty($this->fromer)) return false;
        if (!$this->checkLevelPrize(PrizeService::PRIZE_03, $this->fromer['vip_number'])) return false;
        // 创建返利奖励记录
        $map = ['type' => PrizeService::PRIZE_03, 'order_no' => $this->order['order_no'], 'order_uid' => $this->order['uid']];
        if ($this->app->db->name('DataUserRebate')->where($map)->count() < 1) {
            $amount = sysconf('shop.repeatValue') * $this->order['amount_total'] / 100;
            $name = PrizeService::instance()->name(PrizeService::PRIZE_03) . "，订单 " . sysconf('shop.repeatValue') . '%';
            $this->app->db->name('DataUserRebate')->insert(array_merge($map, [
                'uid' => $this->fromer['id'], 'name' => $name, 'amount' => $amount, 'order_amount' => $this->order['amount_total'],
            ]));
            // 更新会员奖利金额
            UserService::instance()->syncLevel($this->fromer['id']);
        }
        return true;
    }

    /**
     * 间接团队
     * @return boolean
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function _prize04(): bool
    {
        if (empty($this->fromer)) return false;
        $pm2 = $this->app->db->name('DataUser')->where(['id' => $this->fromer['from']])->find();
        if (empty($pm2)) return false;
        if (!$this->checkLevelPrize(PrizeService::PRIZE_04, $pm2['vip_number'])) return false;
        $map = ['type' => PrizeService::PRIZE_04, 'order_no' => $this->order['order_no'], 'order_uid' => $this->order['uid']];
        if ($this->app->db->name('DataUserRebate')->where($map)->count() < 1) {
            $amount = sysconf('shop.indirectValue') * $this->order['amount_total'] / 100;
            $name = PrizeService::instance()->name(PrizeService::PRIZE_04) . "，订单 " . sysconf('shop.indirectValue') . '%';
            $this->app->db->name('DataUserRebate')->insert(array_merge($map, [
                'uid' => $pm2['id'], 'name' => $name, 'amount' => $amount, 'order_amount' => $this->order['amount_total'],
            ]));
            // 更新代理奖利金额
            UserService::instance()->syncLevel($pm2['id']);
        }
        return true;
    }

    /**
     * 差额奖励
     * @return false
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    private function _prize05(): bool
    {
        $pids = array_reverse(explode('-', trim($this->user['path'], '-')));
        if (empty($pids)) return false;
        // 获取拥有差额奖励的等级
        $query = $this->app->db->name('DataUserLevel');
        $numbs = $query->whereLike('rule', '%,' . PrizeService::PRIZE_05 . ',%')->column('number');
        // 获取可以参与奖励的代理
        $query = $this->app->db->name('DataUser')->whereIn('vip_number', $numbs);
        $users = $query->whereIn('id', $pids)->orderField('id', $pids)->select()->toArray();
        // 查询需要计算奖励的商品
        $map = [['order_no', '=', $this->order['order_no']], ['discount_rate', '<', 100]];
        $this->app->db->name('StoreOrderItem')->where($map)->select()->each(function ($item) use ($users) {
            foreach ($users as $user) {
                $map = [
                    'order_no' => $this->order['order_no'],
                    '',
                ];

                $discountRule = $this->app->db->name('DataUserDiscount')->where(['status' => '1', 'is_deleted' => '0'])->value('rule');
                if (!empty($discountRule) && is_array($rules = json_decode($discountRule, true))) {
                    [$tempLevel, $tempRate] = [$item['vip_number'], $item['discount_rate']];
                    foreach ($rules as $rule) if ($rule['level'] > $tempLevel) foreach ($uids as $mem) if ($mem['vip_number'] > $tempLevel) {
                        if ($tempRate > $rule['discount'] && $tempRate < 100) {
                            $diffRate = $tempRate - $rule['discount'];
                            $this->orderno = "{$this->order['order_no']}#{$tempLevel}-{$mem['vip_number']}";
                            if ($this->app->db->name('DataUserRebate')->where(['order_no' => $this->orderno])->count() < 1) {
                                $this->app->db->name('DataUserRebate')->insert([
                                    'order_no'     => $this->orderno, 'order_uid' => $this->order['mid'],
                                    'order_price'  => $this->order['amount_total'], 'type' => '5', 'mid' => $mem['id'],
                                    'profit_price' => $diffRate * $item['goods_amount_total'] / 100,
                                    'profit_state' => intval(empty($this->settlement)),
                                    'description'  => "等级差额奖励{$tempLevel}#{$mem['vip_number']}商品金额{$diffRate}%",
                                ]);
                                $this->app->db->name('StoreOrderItem')->where(['id' => $item['id']])->update(['discount_state' => '1']);
                            }
                            [$tempLevel, $tempRate] = [$mem['vip_number'], $rule['discount']];
                        }
                    }
                }
            }
        });
        return true;
    }

    /**
     * 检查等级是否有奖励
     * @param string $prize
     * @param integer $level
     * @return boolean
     */
    protected function checkLevelPrize(string $prize, int $level): bool
    {
        $map = [['number', '=', $level], ['rebate_rule', 'like', "%,{$prize},%"]];
        return $this->app->db->name('DataUserLevel')->where($map)->count() > 0;
    }
}